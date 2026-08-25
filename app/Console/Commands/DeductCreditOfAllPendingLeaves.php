<?php

namespace App\Console\Commands;

use App\Models\LeaveApplication;
use Illuminate\Console\Command;

class DeductCreditOfAllPendingLeaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deduct-credit-of-all-pending-leaves';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $validStatuses = ['pending', 'for approval', 'for disapproval', 'certified'];

        $leavesToReconcile = LeaveApplication::with([
            'leaveDates',
            'currentStatus'
        ])
            ->whereHas('currentStatus', function ($q) use ($validStatuses) {
                $q->whereIn('status', $validStatuses);
            })
            ->get();

        $service = app(\App\Services\MyLeavesService::class);

        foreach ($leavesToReconcile as $leave) {

            try {
                $service->deduct($leave);
                $this->info("Deducted: Leave ID {$leave->id}");
            } catch (\Exception $e) {
                $this->error("Failed Leave ID {$leave->id}: " . $e->getMessage());
            }
        }

        $this->info('Reconciliation complete.');

    }
}
