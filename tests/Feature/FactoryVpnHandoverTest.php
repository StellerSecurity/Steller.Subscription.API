<?php
namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FactoryVpnHandoverTest extends TestCase
{
    use RefreshDatabase;
    private function grant(array $overrides = []): array {
        $this->withoutMiddleware();
        $this->travelTo(now()->startOfDay());
        $os = Subscription::create(['user_id' => 1, 'type' => 6, 'expires_at' => now()->addDays(365), 'status' => 1]);
        $vpn = Subscription::create(array_merge(['user_id' => 1, 'type' => 1, 'expires_at' => now()->addDays(60), 'status' => 1], $overrides));
        return [$os, $vpn, ['os_subscription_id' => $os->id, 'vpn_subscription_id' => $vpn->id, 'token' => str_repeat('a', 64)]];
    }
    private function register(array $data) { return $this->postJson('/api/v1/factory-vpn/register', $data); }
    private function activate(array $data) { return $this->postJson('/api/v1/factory-vpn/activate', $data); }

    public function test_customer_gets_ninety_days_and_retries_never_extend_it(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->register($data)->assertOk();
        $this->travel(30)->days();
        $expected = now()->addDays(90);
        $this->activate($data)->assertOk()->assertJson(['completed' => true, 'outcome' => 'activated']);
        $this->assertTrue($vpn->fresh()->expires_at->equalTo($expected));
        $this->travel(20)->days();
        $this->register($data)->assertOk();
        $this->activate($data)->assertOk();
        $this->assertTrue($vpn->fresh()->expires_at->equalTo($expected));
        $this->assertSame(1, DB::table('factory_vpn_handovers')->count());
    }
    public function test_wrong_token_missing_grant_and_reenrollment_are_rejected(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->activate($data)->assertForbidden();
        $this->register($data)->assertOk();
        $this->activate([...$data, 'token' => str_repeat('b', 64)])->assertForbidden();
        $this->register([...$data, 'token' => str_repeat('b', 64)])->assertStatus(409);
        $this->assertTrue($vpn->fresh()->expires_at->equalTo(now()->addDays(60)));
    }
    public function test_paid_update_after_registration_is_preserved_and_consumes_grant(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->register($data)->assertOk();
        $paidExpiry = now()->addDays(30);
        $vpn->forceFill(['expires_at' => $paidExpiry, 'source' => 'DIRECT_VPN_PURCHASE'])->save();
        $this->activate($data)->assertOk()->assertJson(['outcome' => 'existing_subscription_preserved']);
        $this->assertTrue($vpn->fresh()->expires_at->equalTo($paidExpiry));
        $this->assertNotNull(DB::table('factory_vpn_handovers')->first()->activated_at);
    }
    public function test_paid_subscription_cannot_be_enrolled(): void {
        [$os, $vpn, $data] = $this->grant(['source' => 'DIRECT_VPN_PURCHASE']);
        $this->register($data)->assertStatus(409);
    }
    public function test_longer_existing_expiry_is_not_shortened(): void {
        [$os, $vpn, $data] = $this->grant(['expires_at' => now()->startOfDay()->addDays(320)]);
        $expiry = $vpn->expires_at->copy();
        $this->register($data)->assertOk();
        $this->activate($data)->assertOk();
        $this->assertTrue($vpn->fresh()->expires_at->equalTo($expiry));
    }
    public function test_failed_transaction_does_not_consume_grant(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->register($data)->assertOk();
        $vpn->delete();
        $this->activate($data)->assertNotFound();
        $this->assertNull(DB::table('factory_vpn_handovers')->first()->activated_at);
    }
    public function test_wrong_product_cannot_be_registered(): void {
        [$os, $vpn, $data] = $this->grant(['type' => 2]);
        $this->register($data)->assertNotFound();
    }
    public function test_payment_credit_is_preserved_even_if_expiry_matches_baseline(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->register($data)->assertOk();
        DB::table('subscription_payment_credits')->insert([
            'payment_reference' => 'test:paid', 'subscription_id' => $vpn->id,
            'days' => 30, 'expires_at' => $vpn->expires_at, 'created_at' => now(),
        ]);
        $this->activate($data)->assertOk()->assertJson(['outcome' => 'existing_subscription_preserved']);
        $this->assertTrue($vpn->fresh()->expires_at->equalTo(now()->addDays(60)));
    }
    public function test_expired_factory_subscription_gets_full_ninety_days(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->register($data)->assertOk();
        $this->travel(100)->days();
        $vpn->forceFill(['status' => 0])->save();
        $this->activate($data)->assertOk();
        $this->assertTrue($vpn->fresh()->expires_at->equalTo(now()->addDays(90)));
        $this->assertSame(1, (int) $vpn->fresh()->status);
    }
    public function test_handover_routes_require_internal_authentication(): void {
        foreach (['register', 'activate'] as $action) {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/factory-vpn/' . $action, 'POST'));
            $this->assertContains('basicAuth', $route->gatherMiddleware());
        }
    }
    public function test_inactive_unexpired_entitlement_is_not_reactivated(): void {
        [$os, $vpn, $data] = $this->grant();
        $this->register($data)->assertOk();
        $vpn->forceFill(['status' => 0])->save();
        $this->activate($data)->assertOk()->assertJson(['outcome' => 'existing_subscription_preserved']);
        $this->assertSame(0, (int) $vpn->fresh()->status);
    }
}
