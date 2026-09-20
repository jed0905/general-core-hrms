<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_approval_workflow_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_approval_workflow_id')
                ->constrained('leave_approval_workflows')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('step_order');

            $table->enum('approver_type', [
                'immediate_supervisor',
                'higher_supervisor',
                'role',
                'designation',
                'specific_employee',
            ]);

            $table->foreignId('approver_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('approver_role')->nullable();

            $table->boolean('is_required')->default(true);

            $table->timestamps();

            $table->unique(
                ['leave_approval_workflow_id', 'step_order'],
                'leave_workflow_step_order_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_approval_work_flow_steps');
    }
};
