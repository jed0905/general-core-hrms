<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generic, module-keyed approval chains (job requisitions now; offers later).
 * Leave keeps its own leave_approval_workflows. Submitted records copy the
 * resolved steps into their own approval rows, so editing a workflow never
 * changes historical approvals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64);
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['module', 'is_active'], 'approval_workflows_module_active_idx');
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            // immediate_supervisor | higher_supervisor | specific_employee (same meaning as Leave)
            $table->string('approver_type', 32);
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'step_order'], 'approval_workflow_steps_order_unique');
        });

        $now = now();
        $workflowId = DB::table('approval_workflows')->insertGetId([
            'module' => 'job_requisition',
            'name' => 'Standard Requisition Approval',
            'description' => "Requester's supervisor, then that supervisor's supervisor (skipped when there is none).",
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('approval_workflow_steps')->insert([
            ['approval_workflow_id' => $workflowId, 'step_order' => 1, 'approver_type' => 'immediate_supervisor', 'approver_employee_id' => null, 'is_required' => true, 'created_at' => $now, 'updated_at' => $now],
            ['approval_workflow_id' => $workflowId, 'step_order' => 2, 'approver_type' => 'higher_supervisor', 'approver_employee_id' => null, 'is_required' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
