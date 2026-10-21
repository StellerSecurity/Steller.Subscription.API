<?php
namespace App\Telemetry;

use ApplicationInsights\Telemetry_Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Only fixed operation labels, method, status and duration leave the service. */
class RequestTelemetry
{
    public function __construct(private Telemetry_Client $client) {}

    public function trackRequest(Request $request, Response $response): void
    {
        if (!config('application_insights.is_enabled', false)
            || !config('application_insights.instrumentation_key')) return;
        try {
            $method = $request->method();
            if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'], true)) $method = 'OTHER';
            $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
            $duration = max(0, min(3600000, (microtime(true) - $start) * 1000));
            $this->client->trackRequest('api '.$method.' operation', 'https://service.invalid/operation',
                $start, $duration, $response->getStatusCode(), $response->isSuccessful(), [], []);
            $this->client->flush([], false);
        } catch (Throwable $ignored) {
            // Monitoring must never change the API result or log sensitive exceptions.
        }
    }
}
