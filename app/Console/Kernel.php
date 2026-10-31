<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->daily();
        $schedule->command('employee-movements:apply-due')->dailyAt('00:05')->withoutOverlapping();
        $schedule->command('recruitment:expire-offers')->dailyAt('00:10')->withoutOverlapping();
        // Attendance pull (recovery path). Devices with a healthy HTTP Listening push channel are skipped
        // except for a periodic catch-up (config attendance.sync.push_reconcile_minutes); devices without
        // push continue from their cursor every minute. A skipped device costs one database read.
        // The overlap lock expires after 10 minutes so a killed run can't block syncing for a day.
        $schedule->command('attendance:sync-devices')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/attendance-sync.log'));
        // Daily reconciliation: re-read the last 24 hours for events a device stores late (duplicates are ignored).
        $schedule->command('attendance:sync-devices --lookback=1440')
            ->dailyAt((string) config('attendance.sync.lookback_daily_at', '02:30'))
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/attendance-sync.log'));
        $schedule->command('leaves:credit')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
