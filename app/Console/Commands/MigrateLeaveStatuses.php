<?php

namespace App\Console\Commands;

use App\Models\LeaveApplication;
use App\Models\LeaveStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLeaveStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-leave-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing leave application statuses to leave_statuses table and set current_status_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of leave statuses...');

        $leaveApplications = LeaveApplication::with('employee.immediateSupervisor')->get();
        $total = $leaveApplications->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($leaveApplications as $leave) {

            if ($leave->current_status_id) {
                $bar->advance();
                continue;
            }

            $oldStatus = strtolower($leave->status);

            DB::transaction(function () use ($leave, $oldStatus) {

                $statuses = [];

                // Default actors
                $supervisorId = $leave->employee?->immediateSupervisor?->id;
                $hrHeadId = $leave->employee?->hrHead()?->id;
                $finalApproverId = $leave->employee?->head()?->id;

                // Always start with pending
                $statuses[] = [
                    'status' => 'pending',
                    'acted_by' => null,
                    'remarks' => null,
                ];

                switch ($oldStatus) {

                    case 'pending':
                        break;

                    case 'recommended':
                        $statuses[] = [
                            'status' => 'for approval',
                            'acted_by' => $supervisorId,
                            'remarks' => null,
                        ];
                        break;

                    case 'disapproved':
                        $statuses[] = [
                            'status' => 'for disapproval',
                            'acted_by' => $supervisorId,
                            'remarks' => $leave->remarks,
                        ];
                        break;

                    case 'approved':
                        $statuses[] = [
                            'status' => 'for approval',
                            'acted_by' => $supervisorId,
                            'remarks' => null,
                        ];
                        $statuses[] = [
                            'status' => 'certified',
                            'acted_by' => $hrHeadId,
                            'remarks' => null,
                        ];
                        $statuses[] = [
                            'status' => 'approved',
                            'acted_by' => $finalApproverId,
                            'remarks' => null,
                        ];
                        break;

                    case 'rejected':
                        $statuses[] = [
                            'status' => 'for approval',
                            'acted_by' => $supervisorId,
                            'remarks' => null,
                        ];

                        $statuses[] = [
                            'status' => 'certified',
                            'acted_by' => $hrHeadId,
                            'remarks' => null,
                        ];

                        $statuses[] = [
                            'status' => 'disapproved',
                            'acted_by' => $finalApproverId,
                            'remarks' => $leave->remarks,
                        ];
                        break;

                    case 'cancelled':
                        $statuses[] = [
                            'status' => 'cancelled',
                            'acted_by' => $leave->employee_id,
                            'remarks' => $leave->remarks,
                        ];
                        break;
                }

                $lastStatusId = null;

                foreach ($statuses as $index => $s) {

                    $status = LeaveStatus::create([
                        'leave_application_id' => $leave->id,
                        'status' => $s['status'],
                        'remarks' => $s['remarks'],
                        'acted_by' => $s['acted_by'],
                        'acted_at' => $leave->created_at->copy()->addSeconds($index), // ensure order
                        'created_at' => $leave->created_at->copy()->addSeconds($index),
                        'updated_at' => $leave->created_at->copy()->addSeconds($index),
                    ]);

                    $lastStatusId = $status->id;
                }

                // Set latest status
                $leave->update([
                    'current_status_id' => $lastStatusId
                ]);
            });

            $bar->advance();
        }

        $bar->finish();

        $this->info("\nMigration completed successfully for {$total} leave applications!");
    }
}
