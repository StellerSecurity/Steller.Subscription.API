<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentCreditController extends Controller
{
    /** Read-only VPN entitlement: unlike find(), this never activates deferred access. */
    public function entitlement(string $id): JsonResponse
    {
        $subscription = Subscription::query()->where('type', 1)->findOrFail($id);
        return response()->json($subscription->only(['id', 'type', 'status', 'expires_at']));
    }

    public function credit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => 'required|string|max:36',
            'payment_reference' => 'required|string|max:191',
            'days' => 'required|integer|min:1|max:3650',
            'type' => 'required|integer',
            'coverage_ends_at' => 'sometimes|date',
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            // Serialize credits for this subscription, including different payments.
            $subscription = Subscription::query()->lockForUpdate()
                ->where('type', $data['type'])->findOrFail($data['subscription_id']);
            $credit = DB::table('subscription_payment_credits')
                ->where('payment_reference', $data['payment_reference'])->first();

            if ($credit) {
                abort_unless($credit->subscription_id === (string) $subscription->id
                    && (int) $credit->days === (int) $data['days'], 409, 'Payment reference already used.');

                return response()->json(['credited' => true, 'already_credited' => true,
                    'expires_at' => $credit->expires_at]);
            }

            $base = $subscription->expires_at && $subscription->expires_at->isFuture()
                ? $subscription->expires_at->copy() : Carbon::now();
            if (isset($data['coverage_ends_at'])) {
                abort_unless((int) $data['type'] === 1, 422, 'Invoice coverage is VPN-only.');
                $coverageEnd = Carbon::parse($data['coverage_ends_at']);
                $expiresAt = $subscription->expires_at && $subscription->expires_at->gt($coverageEnd)
                    ? $subscription->expires_at->copy() : $coverageEnd;
            } else {
                $expiresAt = $base->addDays((int) $data['days']);
            }

            // The unique reference and subscription update commit together. A retried
            // HTTP request after a lost response cannot add the same days again.
            DB::table('subscription_payment_credits')->insert([
                'payment_reference' => $data['payment_reference'],
                'subscription_id' => (string) $subscription->id,
                'days' => (int) $data['days'],
                'expires_at' => $expiresAt,
                'created_at' => Carbon::now(),
            ]);
            $subscription->forceFill(['expires_at' => $expiresAt,
                'status' => $expiresAt->isFuture() ? SubscriptionStatus::ACTIVE->value : SubscriptionStatus::INACTIVE->value])->save();

            return response()->json(['credited' => true, 'already_credited' => false,
                'expires_at' => $expiresAt]);
        }, 3);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => 'required|string|max:36',
            'payment_reference' => 'required|string|max:191',
        ]);
        $credit = DB::table('subscription_payment_credits')->where($data)->first();

        return response()->json(['credited' => $credit !== null, 'supports_coverage_end' => true]);
    }
}
