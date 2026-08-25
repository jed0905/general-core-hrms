<?php

use Carbon\Carbon;
use App\Jobs\ProcessLeaveCredit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// For processing leave credits to eligible employees based on employee leave scheduler table
Artisan::command('leaves:credit', function () {

    $today = Carbon::now()->day;

    $schedules = DB::table('employee_leave_schedulers')
        ->where('run_day', $today)
        ->get();

    if ($schedules->isEmpty()) {
        $this->info("No leave schedules to process today ({$today}).");
        return;
    }

    foreach ($schedules as $schedule) {
        ProcessLeaveCredit::dispatch($schedule);
    }

    $this->info("Queued " . $schedules->count() . " leave credit jobs for processing.");


})->purpose('Process monthly leave credits');
