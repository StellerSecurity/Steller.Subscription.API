<?php
namespace Tests\Feature;

use App\Telemetry\RequestTelemetry;
use ApplicationInsights\Telemetry_Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictedRequestTelemetryTest extends \Tests\TestCase
{
    public function test_only_fixed_operation_metadata_is_sent(): void
    {
        config(['application_insights.is_enabled'=>true,'application_insights.instrumentation_key'=>'test-key']);
        $client = \Mockery::mock(Telemetry_Client::class);
        $client->shouldReceive('trackRequest')->once()->withArgs(function(...$args): bool {
            $this->assertSame('api POST operation', $args[0]);
            $this->assertSame('https://service.invalid/operation', $args[1]);
            $this->assertSame(201, $args[4]);
            $this->assertSame([], $args[6]);
            $this->assertSame([], $args[7]);
            $this->assertStringNotContainsString('synthetic-secret', json_encode($args));
            return true;
        });
        $client->shouldReceive('flush')->once()->with([],false);
        $request = Request::create('https://synthetic-secret.invalid/synthetic-secret?token=synthetic-secret', 'POST',
            ['password'=>'synthetic-secret'], [], [], ['HTTP_AUTHORIZATION'=>'Bearer synthetic-secret']);
        (new RequestTelemetry($client))->trackRequest($request,new Response('synthetic-secret',201));
    }

    public function test_disabled_telemetry_sends_nothing(): void
    {
        config(['application_insights.is_enabled'=>false,'application_insights.instrumentation_key'=>'test-key']);
        $client = \Mockery::mock(Telemetry_Client::class);
        $client->shouldNotReceive('trackRequest');$client->shouldNotReceive('flush');
        (new RequestTelemetry($client))->trackRequest(Request::create('/'),new Response());
    }

    public function test_telemetry_failure_does_not_escape(): void
    {
        config(['application_insights.is_enabled'=>true,'application_insights.instrumentation_key'=>'test-key']);
        $client = \Mockery::mock(Telemetry_Client::class);
        $client->shouldReceive('trackRequest')->once()->andThrow(new \RuntimeException('synthetic-secret'));
        $client->shouldNotReceive('flush');
        (new RequestTelemetry($client))->trackRequest(Request::create('/'),new Response());
        $this->assertTrue(true);
    }

    public function test_no_database_query_telemetry_listener_is_registered(): void
    {
        $this->assertFalse(app('events')->hasListeners(\Illuminate\Database\Events\QueryExecuted::class));
    }
}
