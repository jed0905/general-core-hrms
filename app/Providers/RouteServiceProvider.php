<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
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

        // Careers portal (public): generous for browsing, strict for sign-in/registration/reset, per-candidate for applying.
        RateLimiter::for('careers-browse', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('careers-auth', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.mb_strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('careers-apply', fn (Request $request) => Limit::perMinute(10)->by('applicant:'.($request->user('applicant')?->id ?: $request->ip())));

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Public careers portal: no employee login; candidates use the "applicant" guard.
            Route::middleware('web')
                ->group(base_path('routes/Careers/web.php'));

            // Administration Routes
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Administration/web.php'));

            // People Routes
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/People/web.php'));

            // Time Routes
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Time/web.php'));

            // Leave Routes
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Leave/web.php'));

            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Payroll/web.php'));

            // Core HR Reports
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Reports/web.php'));

            // Talent & Recruitment
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Recruitment/web.php'));

            // Attendance (devices, logs, daily attendance, adjustments)
            Route::middleware(['auth', 'web'])
                ->group(base_path('routes/Attendance/web.php'));

            // Device- and connector-initiated requests: stateless (no session/CSRF),
            // authenticated per device/connector, no URL prefix (ZKTeco PUSH needs /iclock/*).
            // Not the "api" group: its per-IP limit would throttle several devices behind one NAT;
            // each device route sets its own throttle.
            Route::middleware(SubstituteBindings::class)
                ->group(base_path('routes/Attendance/device.php'));
        });
    }
}
