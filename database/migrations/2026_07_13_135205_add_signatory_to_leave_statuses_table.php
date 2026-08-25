<?php

use App\Models\LeaveStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('signatory_id')
                ->nullable()
                ->after('acted_by');

            $table->foreign('signatory_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });

        // Backfill existing records
        LeaveStatus::with('leaveApplication.employee')
            ->whereIn('status', [
                'certified',
                'approved',
                'for approval',
                'for disapproval',
            ])
            ->chunkById(100, function ($leaveStatuses) {
                foreach ($leaveStatuses as $leaveStatus) {

                    $employee = optional($leaveStatus->leaveApplication)->employee;

                    if (!$employee) {
                        continue;
                    }

                    $signatoryId = match ($leaveStatus->status) {
                        'certified' => optional($employee->hrHead($leaveStatus->created_at))->id,
                        'approved' => optional($employee->head($leaveStatus->created_at))->id,
                        'disapproved' => optional($employee->head($leaveStatus->created_at))->id,
                        'for approval', 'for disapproval' => $leaveStatus->acted_by,
                        default => null,
                    };

                    if ($signatoryId) {
                        $leaveStatus->updateQuietly([
                            'signatory_id' => $signatoryId,
                        ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_statuses', function (Blueprint $table) {
            $table->dropForeign(['signatory_id']);
            $table->dropColumn('signatory_id');
        });
    }
};
