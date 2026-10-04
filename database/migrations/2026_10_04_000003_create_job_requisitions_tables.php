<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job requisitions (internal requests to hire) and their approval instances.
 * Position data references Core HR master tables; no duplicates are created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number', 64)->unique();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('job_title_id')->constrained('job_titles')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('employment_status_id')->nullable()->constrained('employment_statuses')->restrictOnDelete();
            $table->unsignedSmallInteger('positions');
            $table->enum('reason', ['new_position', 'replacement']);
            $table->foreignId('replaced_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('justification');
            $table->date('target_start_date')->nullable();
            $table->foreignId('requested_by_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();

            $table->index('status', 'job_requisitions_status_idx');
            $table->index(['department_id', 'status'], 'job_requisitions_dept_status_idx');
        });

        Schema::create('job_requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            // References for traceability only; the snapshot columns below are what count.
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->foreignId('approval_workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->string('workflow_name');
            $table->unsignedSmallInteger('approval_order');
            $table->string('approver_type', 32);
            $table->boolean('is_required')->default(true);
            $table->foreignId('approver_id')->constrained('employees')->restrictOnDelete();
            $table->string('approver_name');
            $table->enum('status', ['pending', 'approved', 'rejected', 'skipped'])->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['job_requisition_id', 'approval_order'], 'job_req_approvals_order_unique');
            $table->index(['approver_id', 'status'], 'job_req_approvals_approver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requisition_approvals');
        Schema::dropIfExists('job_requisitions');
    }
};
