<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Period inputs that feed a payroll calculation.
 *
 * - payroll_time_inputs: quantities per employee per period (days/hours worked,
 *   absences, late and undertime hours, premium hours by localization category).
 *   Only approved rows are read by the calculation. There is no DTR engine yet,
 *   so these are entered or imported by payroll staff; a future DTR provider
 *   would produce the same quantities.
 * - payroll_adjustments: one-time earnings/deductions (bonus, commission,
 *   corrections). Approved rows are applied by the next run of their period and
 *   become "applied" when that run is finalized.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_time_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('category', 48);
            $table->decimal('quantity', 12, 4);
            $table->string('source', 24)->default('manual');
            $table->string('remarks')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id', 'category'], 'payroll_time_inputs_unique');
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('corrects_run_employee_id')->nullable(); // FK added with the run tables
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('applied_run_id')->nullable(); // FK added with the run tables
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_period_id', 'status'], 'payroll_adjustments_period_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payroll_time_inputs ADD CONSTRAINT payroll_time_inputs_status_check CHECK (status IN ('pending', 'approved'))");
            DB::statement('ALTER TABLE payroll_time_inputs ADD CONSTRAINT payroll_time_inputs_quantity_check CHECK (quantity >= 0)');
            DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT payroll_adjustments_status_check CHECK (status IN ('pending', 'approved', 'applied'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_time_inputs');
    }
};
