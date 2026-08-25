<?php

namespace App\Console\Commands;

use App\Models\LeaveApplication;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SetLeaveApplicationStatusToExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-leave-application-status-to-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically set leave application status to expired every midnight';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Expire any pending leave whose end date is strictly before today
        LeaveApplication::where('status', 'pending')
            ->whereDate('to', '<', now('Asia/Manila')->toDateString())
            ->update(['status' => 'expired']);

        $this->info('Pending leave applications with past end dates set to expired');
    }
}
