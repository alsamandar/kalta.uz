<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Separate buckets, so typing in the QR generator does not use up the "create a link" budget
        RateLimiter::for('create', fn (Request $request) => Limit::perMinute(20)->by('create|' . $request->ip()));
        RateLimiter::for('qr', fn (Request $request) => Limit::perMinute(90)->by('qr|' . $request->ip()));
        RateLimiter::for('invite', fn (Request $request) => Limit::perMinute(30)->by('invite|' . $request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(40)->by('uploads|' . $request->ip()));
        RateLimiter::for('manage', fn (Request $request) => Limit::perMinute(120)->by('manage|' . $request->ip()));

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
