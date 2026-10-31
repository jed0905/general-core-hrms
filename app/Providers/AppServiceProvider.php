<?php

namespace App\Providers;

use App\Services\Attendance\AttendanceTimeInputProvider;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Payroll\Contracts\LeaveInputProvider;
use App\Services\Payroll\Contracts\TimeInputProvider;
use App\Services\Payroll\Inputs\ApprovedLeaveProvider;
use App\Services\Payroll\Localization\LocalizationRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Payroll: country localizations keyed by ISO 3166-1 alpha-2 code (config/payroll.php).
        $this->app->singleton(LocalizationRegistry::class, fn () => new LocalizationRegistry(config('payroll.localizations', [])));
        // Attendance-aware time source; pay groups on "manual_inputs" keep the approved manual inputs only.
        $this->app->bind(TimeInputProvider::class, AttendanceTimeInputProvider::class);
        $this->app->bind(LeaveInputProvider::class, ApprovedLeaveProvider::class);

        // Attendance: biometric device adapters keyed by vendor (config/attendance.php).
        $this->app->singleton(DeviceAdapterRegistry::class, fn () => new DeviceAdapterRegistry(config('attendance.adapters', [])));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $envVersionPath = base_path('.env.version');
        if (file_exists($envVersionPath)) {
            foreach (file($envVersionPath) as $line) {
                putenv(trim($line));
            }
        }

        config(['app.version' => getenv('APP_VERSION') ?: config('app.version')]);
    }
}
