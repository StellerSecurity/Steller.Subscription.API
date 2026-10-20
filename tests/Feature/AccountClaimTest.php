<?php
namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

final class AccountClaimTest extends \Tests\TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutMiddleware(); }
    private function data(): array { return ['provisional_user_id'=>'123','token'=>str_repeat('a',64),'username'=>'person@example.com','target_user_id'=>'456']; }
    private function callClaim(string $action,array $changes=[]) { return $this->postJson('/api/v1/account-claims/'.$action,array_merge($this->data(),$changes)); }
    private function grants(): array {
        return [Subscription::create(['user_id'=>123,'type'=>1,'status'=>1,'expires_at'=>now()->addDays(90)]),
            Subscription::create(['user_id'=>123,'type'=>2,'status'=>1,'expires_at'=>now()->addDays(320)])];
    }
    public function test_atomic_transfer_and_retry_preserve_expiry(): void {
        [$vpn,$av]=$this->grants();$expiry=$vpn->expires_at;
        $this->callClaim('register')->assertOk();$this->callClaim('authorize')->assertOk();
        $this->callClaim('transfer')->assertOk();$this->callClaim('transfer')->assertOk();
        $this->assertEquals(456,$vpn->fresh()->user_id);$this->assertEquals(456,$av->fresh()->user_id);
        $this->assertTrue($vpn->fresh()->expires_at->equalTo($expiry));
        $this->callClaim('status')->assertJson(['protected'=>true]);
        $this->callClaim('transfer',['target_user_id'=>'789'])->assertForbidden();
    }
    public function test_wrong_proof_and_destination_are_rejected(): void {
        $this->callClaim('register')->assertOk();
        $this->callClaim('authorize',['token'=>str_repeat('b',64)])->assertForbidden();
        $this->callClaim('authorize')->assertOk();
        $this->callClaim('authorize',['username'=>'other@example.com'])->assertForbidden();
        $this->callClaim('register',['token'=>str_repeat('b',64)])->assertStatus(409);
    }
    public function test_failed_save_rolls_back_both_ownership_and_consumption(): void {
        [$vpn,$av]=$this->grants();$this->callClaim('register');$this->callClaim('authorize');
        Subscription::updating(function($model) { if((int)$model->type===2) throw new \RuntimeException('test failure'); });
        try { $this->callClaim('transfer')->assertStatus(500); }
        finally { Subscription::flushEventListeners(); }
        $this->assertEquals(123,$vpn->fresh()->user_id);$this->assertEquals(123,$av->fresh()->user_id);
        $this->assertNull(DB::table('provisional_account_claims')->first()->consumed_at);
    }
    public function test_expired_claim_and_unbound_transfer_are_rejected(): void {
        $this->callClaim('register');$this->callClaim('transfer')->assertForbidden();
        $this->travel(367)->days();$this->callClaim('authorize')->assertForbidden();
    }
    public function test_old_activation_remains_unprotected(): void { $this->callClaim('status')->assertJson(['protected'=>false]); }
    public function test_internal_routes_require_authentication_middleware(): void {
        foreach(['ready','status','register','authorize','transfer'] as $action) {
            $route=app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/account-claims/'.$action,'POST'));
            $this->assertContains('basicAuth',$route->gatherMiddleware());
        }
    }
}
