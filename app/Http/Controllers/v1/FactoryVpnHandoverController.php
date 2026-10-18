<?php
namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FactoryVpnHandoverController extends Controller
{
    // Internal provisioning only. Never expose this route without basicAuth.
    public function register(Request $request)
    {
        $data = $request->validate([
            'os_subscription_id' => 'required|string|max:36',
            'vpn_subscription_id' => 'required|string|max:36',
            'token' => 'required|string|size:64|regex:/^[a-f0-9]+$/',
        ]);
        return DB::transaction(function () use ($data) {
            $os = Subscription::query()->where('type', 6)->lockForUpdate()
                ->findOrFail($data['os_subscription_id']);
            $vpn = Subscription::query()->where('type', 1)->lockForUpdate()
                ->findOrFail($data['vpn_subscription_id']);
            $existing = DB::table('factory_vpn_handovers')
                ->where('os_subscription_id', $os->id)->first();
            $hash = hash('sha256', $data['token']);
            if ($existing) {
                abort_unless($existing->vpn_subscription_id === (string) $vpn->id
                    && hash_equals($existing->token_hash, $hash), 409);
                return response()->json(['registered' => true]);
            }
            abort_if(DB::table('factory_vpn_handovers')->where('vpn_subscription_id', $vpn->id)->exists(), 409);
            // Paid/externally issued entitlements cannot be enrolled as a factory grant.
            abort_if($vpn->plan_id !== null || $vpn->source !== null
                || DB::table('subscription_payment_credits')->where('subscription_id', $vpn->id)->exists(), 409);
            DB::table('factory_vpn_handovers')->insert([
                'os_subscription_id' => (string) $os->id,
                'vpn_subscription_id' => (string) $vpn->id,
                'token_hash' => $hash,
                'baseline' => json_encode($this->baseline($vpn), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            return response()->json(['registered' => true]);
        }, 3);
    }

    public function activate(Request $request)
    {
        $data = $request->validate([
            'os_subscription_id' => 'required|string|max:36',
            'token' => 'required|string|size:64|regex:/^[a-f0-9]+$/',
        ]);
        return DB::transaction(function () use ($data) {
            $grant = DB::table('factory_vpn_handovers')->where('os_subscription_id', $data['os_subscription_id'])
                ->lockForUpdate()->first();
            abort_unless($grant && hash_equals($grant->token_hash, hash('sha256', $data['token'])), 403);
            // Consume atomically with expiry update; lost responses and concurrent retries are safe.
            if ($grant->activated_at !== null) {
                return response()->json(['completed' => true, 'outcome' => $grant->outcome,
                    'activated_at' => $grant->activated_at, 'expires_at' => $grant->expires_at]);
            }
            abort_unless(Subscription::query()->where('type', 6)->find($data['os_subscription_id']), 403);
            $vpn = Subscription::query()->where('type', 1)->lockForUpdate()->findOrFail($grant->vpn_subscription_id);
            // An inactive, unexpired entitlement may have been administratively revoked.
            $unchanged = ((int) $vpn->status === SubscriptionStatus::ACTIVE->value
                    || ($vpn->expires_at && $vpn->expires_at->isPast()))
                && $this->baseline($vpn) === json_decode($grant->baseline, true, 512, JSON_THROW_ON_ERROR)
                && !DB::table('subscription_payment_credits')->where('subscription_id', $vpn->id)->exists();
            $now = now();
            $outcome = 'existing_subscription_preserved';
            if ($unchanged) {
                $expires = $now->copy()->addDays(90);
                if ($vpn->expires_at && $vpn->expires_at->greaterThan($expires)) $expires = $vpn->expires_at;
                $vpn->forceFill(['expires_at' => $expires, 'status' => SubscriptionStatus::ACTIVE->value])->save();
                $outcome = 'activated';
            }
            DB::table('factory_vpn_handovers')->where('os_subscription_id', $data['os_subscription_id'])->update([
                'activated_at' => $now, 'expires_at' => $vpn->expires_at, 'outcome' => $outcome,
            ]);
            return response()->json(['completed' => true, 'outcome' => $outcome,
                'activated_at' => $now, 'expires_at' => $vpn->expires_at]);
        }, 3);
    }

    private function baseline(Subscription $vpn): array
    {
        return ['expires_at' => $vpn->expires_at?->format('Y-m-d H:i:s'),
            'plan_id' => $vpn->plan_id, 'source' => $vpn->source,
            'source_meta' => $vpn->source_meta, 'meta' => $vpn->meta];
    }
}
