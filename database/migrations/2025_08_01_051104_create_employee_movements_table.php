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
        Schema::create('employee_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            // Movement Details
            $table->enum('movement_type', [
                'promotion',
                'transfer',
                'reassignment',
                'demotion',
                'reinstatement',
                'reemployment',
                'separation',
                'retirement',
                'resignation',
                'termination',
                'suspension',
                'salary_adjustment',
                'position_change',
                'department_change',
                'designation_change'
            ]);
            
            // Previous Position/Department/Designation
            $table->foreignId('previous_position_id')->nullable()->constrained('positions');
            $table->foreignId('previous_department_id')->nullable()->constrained('departments');
            $table->foreignId('previous_designation_id')->nullable()->constrained('designations');
            $table->foreignId('previous_operating_unit_id')->nullable()->constrained('operating_units');
            $table->decimal('previous_salary', 10, 2)->nullable();
            $table->string('previous_salary_grade')->nullable();
            $table->string('previous_salary_step')->nullable();
            
            // New Position/Department/Designation
            $table->foreignId('new_position_id')->nullable()->constrained('positions');
            $table->foreignId('new_department_id')->nullable()->constrained('departments');
            $table->foreignId('new_designation_id')->nullable()->constrained('designations');
            $table->foreignId('new_operating_unit_id')->nullable()->constrained('operating_units');
            $table->decimal('new_salary', 10, 2)->nullable();
            $table->string('new_salary_grade')->nullable();
            $table->string('new_salary_step')->nullable();
            
            // Movement Dates
            $table->date('effective_date');
            $table->date('approval_date')->nullable();
            $table->date('implementation_date')->nullable();
            
            // Status and Approval
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'implemented',
                'cancelled'
            ])->default('pending');
            
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->text('justification')->nullable();
            
            // Approval Information
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            
            // Document References
            $table->string('document_number')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_file_path')->nullable();
            
            // Additional Information
            $table->boolean('is_permanent')->default(false);
            $table->boolean('is_temporary')->default(false);
            $table->date('temporary_end_date')->nullable();
            $table->text('conditions')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['employee_id', 'movement_type']);
            $table->index(['effective_date']);
            $table->index(['status']);
            $table->index(['approved_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_movements');
    }
};
