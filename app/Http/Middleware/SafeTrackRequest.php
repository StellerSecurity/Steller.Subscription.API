<?php
namespace App\Http\Middleware;

use App\Telemetry\RequestTelemetry;
use Closure;
use Illuminate\Http\Request;

/** Request telemetry never receives credentials, body, query, or caller headers. */
final class SafeTrackRequest
{
    public function __construct(private RequestTelemetry $insights) {}
    public function handle(Request $request, Closure $next) { return $next($request); }
    public function terminate($request, $response): void
    {
        if ($request->attributes->get('stellar_skip_request_telemetry', false)) return;
        if ($request->attributes->get('stellar_request_telemetry_recorded', false)) return;
        $request->attributes->set('stellar_request_telemetry_recorded', true);
        $safe = Request::create('https://subscription.invalid/operation', $request->method());
        $this->insights->trackRequest($safe, $response);
    }
}
