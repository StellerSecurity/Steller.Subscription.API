<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Telemetry\RequestTelemetry::class, function () {
            $client = new \ApplicationInsights\Telemetry_Client();
            $key = config('application_insights.instrumentation_key');
            if (is_string($key) && $key !== '') $client->getContext()->setInstrumentationKey($key);
            return new \App\Telemetry\RequestTelemetry($client);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
