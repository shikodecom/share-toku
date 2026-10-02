<?php

namespace App\Providers;

use App\Contracts\SubscriptionManager;
use App\Services\ManualSubscriptionManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SubscriptionManager::class, ManualSubscriptionManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('site', function (Request $request) {
            $token = $request->bearerToken();

            return [
                Limit::perMinute(config('sharetoku.rate_limit_site_per_minute'))->by('site-token:'.($token ? hash('sha256', $token) : $request->ip())),
                Limit::perMinute(config('sharetoku.rate_limit_ip_per_minute'))->by('site-ip:'.$request->ip()),
            ];
        });
    }
}
