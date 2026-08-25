<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->string('employee_number')->unique();
            $table->string('biometrics_id')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('operating_unit_id')->constrained('operating_units');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('employee_type')->nullable();
            $table->foreignId('job_status_id')->nullable()->constrained('job_statuses');
            $table->foreignId('position_id')->nullable()->constrained('positions');
            $table->string('parenthetical_title')->nullable();

            $table->foreignId('salary_step_id')->nullable()->constrained('salary_steps');
            $table->decimal('custom_hourly_rate')->nullable();
            $table->decimal('custom_daily_rate')->nullable();
            $table->date('date_hired')->nullable();
            $table->date('date_separated')->nullable();
            $table->string('separation_reason')->nullable();
            $table->text('termination_notes')->nullable();

            // PDF related columns
            $table->foreignId('immediate_supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('higher_supervisor_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->foreignId('immediate_supervisor_designation')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('higher_supervisor_designation')->nullable()->constrained('designations')->nullOnDelete();

            // PDS related tables
            $table->string('gov_issued_id')->nullable();
            $table->string('gov_id_number')->nullable();
            $table->date('date_issue')->nullable();
            $table->string('place_issue')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
