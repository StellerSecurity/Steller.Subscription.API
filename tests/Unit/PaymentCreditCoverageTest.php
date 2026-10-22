<?php

namespace Tests\Unit;

use App\Http\Controllers\v1\PaymentCreditController;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class PaymentCreditCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22T12:00:00Z'));
        $container = new Container;
        Container::setInstance($container);
        $container->instance('config', new Repository);
        $db = new Manager($container);
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setEventDispatcher(new Dispatcher($container));
        $db->setAsGlobal();
        $db->bootEloquent();
        $container->instance('db', $db->getDatabaseManager());
        $container->instance('db.schema', $db->schema());
        $factory = $this->createMock(ResponseFactory::class);
        $factory->method('json')->willReturnCallback(fn ($data) => new JsonResponse($data));
        $container->instance(ResponseFactory::class, $factory);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $db->schema()->create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('user_id');
            $table->integer('type');
            $table->json('meta')->nullable();
            $table->timestamp('expires_at');
            $table->integer('status');
            $table->timestamps();
            $table->softDeletes();
        });
        (require __DIR__.'/../../database/migrations/2026_10_16_000001_create_subscription_payment_credits.php')->up();
    }

    protected function tearDown(): void
    {
        app('db')->disconnect();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function credit(Subscription $subscription, string $reference, string $end): array
    {
        $request = new class extends Request
        {
            public function validate(array $rules): array
            {
                return $this->all();
            }
        };
        $request->replace(['subscription_id' => $subscription->id, 'payment_reference' => $reference,
            'days' => 30, 'type' => 1, 'coverage_ends_at' => $end]);

        return (new PaymentCreditController)->credit($request)->getData(true);
    }

    public function test_entitlement_inspection_does_not_activate_or_extend_inactive_vpn(): void
    {
        $subscription = Subscription::create(['user_id' => 1, 'type' => 1, 'status' => 0,
            'expires_at' => now()->subDays(30), 'meta' => ['activated' => false, 'activation_mode' => 'deferred']]);
        $before = $subscription->fresh()->toArray();
        $result = (new PaymentCreditController)->entitlement($subscription->id)->getData(true);
        self::assertSame($subscription->id, $result['id']);
        self::assertSame(0, $result['status']);
        self::assertArrayNotHasKey('meta', $result);
        self::assertSame($before, $subscription->fresh()->toArray());
        self::assertSame(0, app('db')->table('subscription_payment_credits')->count());
    }

    public function test_coverage_replay_and_older_invoices_never_add_or_remove_purchased_time(): void
    {
        $subscription = Subscription::create(['user_id' => 1, 'type' => 1, 'status' => 1, 'expires_at' => now()->addDays(10)]);
        $end = now()->addDays(30)->toIso8601String();
        self::assertFalse($this->credit($subscription, 'stripe:invoice:in_current', $end)['already_credited']);
        self::assertTrue($this->credit($subscription, 'stripe:invoice:in_current', $end)['already_credited']);
        $this->credit($subscription, 'stripe:invoice:in_older', now()->addDays(20)->toIso8601String());
        self::assertTrue($subscription->fresh()->expires_at->equalTo(now()->addDays(30)));
        self::assertSame(2, app('db')->table('subscription_payment_credits')->count());
    }

    public function test_historical_coverage_does_not_create_a_new_active_period_today(): void
    {
        $subscription = Subscription::create(['user_id' => 1, 'type' => 1, 'status' => 0, 'expires_at' => now()->subDays(60)]);
        $this->credit($subscription, 'stripe:invoice:in_historical', now()->subDays(30)->toIso8601String());
        self::assertTrue($subscription->fresh()->expires_at->equalTo(now()->subDays(30)));
        self::assertSame(0, $subscription->fresh()->status);
    }
}
