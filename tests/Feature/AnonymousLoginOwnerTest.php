<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class AnonymousLoginOwnerTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
            throw new \RuntimeException('In-memory testing database required.');
        }
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\BasicAuth::class);
    }
    public function test_owner_lookup_does_not_activate_or_mutate_a_deferred_vpn_subscription(): void
    {
        $subscription = Subscription::create(['id' => '0000000000000042', 'user_id' => 42, 'type' => 1, 'status' => 0,
            'meta' => ['activate_on_login' => true, 'activated' => false, 'activation_days' => 30]]);
        $before = $subscription->fresh()->toArray();
        $this->postJson('/api/v1/subscriptioncontroller/anonymous-login-owner', ['account_number' => $subscription->id])
            ->assertOk()->assertExactJson(['account_number' => $subscription->id, 'user_id' => '42', 'type' => 1])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, $subscription->fresh()->toArray());
        $subscription->forceFill(['type' => 2])->save();
        $this->postJson('/api/v1/subscriptioncontroller/anonymous-login-owner', ['account_number' => $subscription->id])->assertNotFound();
        $subscription->forceFill(['type' => 1])->save(); $subscription->delete();
        $this->postJson('/api/v1/subscriptioncontroller/anonymous-login-owner', ['account_number' => $subscription->id])->assertNotFound();
    }
    public function test_invalid_numbers_are_rejected_without_creating_a_subscription(): void
    {
        foreach (['abc', [], '123', '00000000000000420'] as $number) {
            $this->postJson('/api/v1/subscriptioncontroller/anonymous-login-owner', ['account_number' => $number])->assertStatus(422);
        }
        $this->postJson('/api/v1/subscriptioncontroller/anonymous-login-owner', ['account_number' => '0000000000000043'])->assertNotFound();
        $this->assertSame(0, Subscription::count());
    }
}
