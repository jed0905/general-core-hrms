<?php

namespace App\Console;

use App\Jobs\CheckTardinessJob;
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
        $schedule->command('leaves:credit')->daily();
        $schedule->job(new CheckTardinessJob)
            ->dailyAt('09:30')
            ->withoutOverlapping();
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
