<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of the workflow step each approval instance was resolved from,
     * so later workflow edits never change the meaning of past approvals.
     */
    public function up(): void
    {
        Schema::table('leave_application_approvals', function (Blueprint $table) {
            $table->foreignId('leave_approval_workflow_id')
                ->nullable()
                ->after('leave_application_id');
            $table->foreign('leave_approval_workflow_id', 'leave_app_appr_workflow_fk')
                ->references('id')
                ->on('leave_approval_workflows')
                ->nullOnDelete();

            $table->foreignId('leave_approval_workflow_step_id')
                ->nullable()
                ->after('leave_approval_workflow_id');
            $table->foreign('leave_approval_workflow_step_id', 'leave_app_appr_step_fk')
                ->references('id')
                ->on('leave_approval_workflow_steps')
                ->nullOnDelete();

            $table->string('approver_type', 50)->nullable()->after('approver_id');
            $table->string('approver_name')->nullable()->after('approver_type');
            $table->boolean('is_required')->default(true)->after('approval_order');
        });
    }

    public function down(): void
    {
        // SQLite (tests) can only drop foreign keys by column.
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('leave_application_approvals', function (Blueprint $table) use ($sqlite) {
            $table->dropForeign($sqlite ? ['leave_approval_workflow_id'] : 'leave_app_appr_workflow_fk');
            $table->dropForeign($sqlite ? ['leave_approval_workflow_step_id'] : 'leave_app_appr_step_fk');
            $table->dropColumn([
                'leave_approval_workflow_id',
                'leave_approval_workflow_step_id',
                'approver_type',
                'approver_name',
                'is_required',
            ]);
        });
    }
};
