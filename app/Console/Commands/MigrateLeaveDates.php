<?php

namespace App\Console\Commands;

use App\Models\LeaveApplication;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class MigrateLeaveDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-leave-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert leave application from/to into leave_dates records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Migrating leave dates...');

        LeaveApplication::chunk(50, function ($applications) {
            foreach ($applications as $leave) {
                if (!$leave->from || !$leave->to) {
                    continue; // skip invalid data
                }

                $period = CarbonPeriod::create($leave->from, $leave->to); // inclusive

                foreach ($period as $date) {
                    $leave->leaveDates()->create([
                        'date' => $date->toDateString(),
                        'credits' => 1,
                        'duration' => 'full_day'
                    ]);
                }
            }
        });

        $this->info('Migration completed.');
    }
}
