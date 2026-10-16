<?php

namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_of_one_payment_credits_a_uuid_subscription_only_once(): void
    {
        $this->withoutMiddleware();
        $this->travelTo(now()->startOfDay());
        $subscription = Subscription::create(['user_id' => 1, 'type' => 1,
            'expires_at' => now()->addDays(10), 'status' => 1]);
        $data = ['subscription_id' => $subscription->id, 'payment_reference' => 'stripe:pi:example',
            'days' => 30, 'type' => 1];

        $this->postJson('/api/v1/subscriptioncontroller/credit', $data)
            ->assertOk()->assertJson(['credited' => true, 'already_credited' => false]);
        $this->postJson('/api/v1/subscriptioncontroller/credit', $data)
            ->assertOk()->assertJson(['credited' => true, 'already_credited' => true]);
        $this->assertTrue($subscription->fresh()->expires_at->equalTo(now()->addDays(40)));
        $this->assertSame(1, DB::table('subscription_payment_credits')->count());

        $data['payment_reference'] = 'stripe:pi:another';
        $this->postJson('/api/v1/subscriptioncontroller/credit', $data)->assertOk();
        $this->assertTrue($subscription->fresh()->expires_at->equalTo(now()->addDays(70)));
    }

    public function test_reference_cannot_be_reused_for_another_subscription_or_amount(): void
    {
        $this->withoutMiddleware();
        $subscription = Subscription::create(['user_id' => 1, 'type' => 1,
            'expires_at' => now(), 'status' => 0]);
        $other = Subscription::create(['user_id' => 2, 'type' => 1,
            'expires_at' => now(), 'status' => 0]);
        $data = ['subscription_id' => $subscription->id, 'payment_reference' => 'stripe:pi:example',
            'days' => 30, 'type' => 1];
        $this->postJson('/api/v1/subscriptioncontroller/credit', $data)->assertOk();
        $this->postJson('/api/v1/subscriptioncontroller/credit', [...$data, 'days' => 365])->assertStatus(409);
        $this->postJson('/api/v1/subscriptioncontroller/credit', [...$data, 'subscription_id' => $other->id])->assertStatus(409);
        $this->getJson('/api/v1/subscriptioncontroller/credit-status?' . http_build_query([
            'subscription_id' => $other->id, 'payment_reference' => $data['payment_reference'],
        ]))->assertOk()->assertJson(['credited' => false]);
    }

    public function test_missing_or_wrong_type_subscription_cannot_be_credited(): void
    {
        $this->withoutMiddleware();
        $subscription = Subscription::create(['user_id' => 1, 'type' => 0,
            'expires_at' => now(), 'status' => 0]);
        $this->postJson('/api/v1/subscriptioncontroller/credit', [
            'subscription_id' => $subscription->id, 'payment_reference' => 'stripe:pi:wrong-type',
            'days' => 30, 'type' => 1,
        ])->assertNotFound();
        $this->assertSame(0, DB::table('subscription_payment_credits')->count());
    }
}
