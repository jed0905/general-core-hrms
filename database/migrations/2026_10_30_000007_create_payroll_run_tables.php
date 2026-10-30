<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll runs, per-employee results (payslips), lines, exceptions and events.
 *
 * - Lifecycle: draft -> calculated -> reviewed -> approved -> finalized (cancelled
 *   before finalization). Finalized runs, their payslips and lines are never
 *   updated or deleted; corrections are reversal / supplemental runs.
 * - At most one non-cancelled regular run per period, and one non-cancelled
 *   reversal per original run: unique indexes on generated columns.
 * - Every line stores its inputs and the statutory rule version it used, and each
 *   payslip stores a snapshot of the compensation and context it was calculated
 *   from, so a finalized run can be reproduced and is never recalculated.
 * - payroll_run_events is the append-only audit log of lifecycle actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number', 64)->unique();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->foreignId('pay_group_id')->constrained('pay_groups')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->string('run_type', 16)->default('regular');
            $table->string('status', 16)->default('draft');
            $table->char('currency', 3);
            $table->foreignId('reverses_run_id')->nullable()->constrained('payroll_runs')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('engine_version', 16)->nullable();
            $table->boolean('uses_unverified_rules')->default(false);

            $table->decimal('gross_total', 19, 4)->default(0);
            $table->decimal('employee_deduction_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('net_total', 19, 4)->default(0);
            $table->decimal('employer_contribution_total', 19, 4)->default(0);
            $table->decimal('employer_cost_total', 19, 4)->default(0);
            $table->unsignedInteger('employee_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unsignedBigInteger('active_regular_period_id')->nullable()
                ->storedAs("case when run_type = 'regular' and status <> 'cancelled' then payroll_period_id end");
            $table->unique('active_regular_period_id', 'payroll_runs_one_regular_unique');
            $table->unsignedBigInteger('active_reversal_of_id')->nullable()
                ->storedAs("case when run_type = 'reversal' and status <> 'cancelled' then reverses_run_id end");
            $table->unique('active_reversal_of_id', 'payroll_runs_one_reversal_unique');

            $table->index(['legal_entity_id', 'status'], 'payroll_runs_entity_status_idx');
            $table->index(['payroll_period_id', 'run_type'], 'payroll_runs_period_type_idx');
        });

        Schema::create('payroll_run_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employment_assignment_id')->constrained('employment_assignments')->restrictOnDelete();
            $table->string('status', 16)->default('included');
            $table->string('exclusion_reason')->nullable();
            $table->string('payslip_number', 64)->nullable()->unique();
            $table->char('currency', 3);
            $table->decimal('gross_pay', 19, 4)->default(0);
            $table->decimal('employee_deductions', 19, 4)->default(0);
            $table->decimal('tax', 19, 4)->default(0);
            $table->decimal('net_pay', 19, 4)->default(0);
            $table->decimal('employer_contributions', 19, 4)->default(0);
            $table->decimal('employer_cost', 19, 4)->default(0);
            $table->decimal('taxable_income', 19, 4)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employment_assignment_id'], 'payroll_run_employees_unique');
            $table->index(['employee_id', 'payroll_run_id'], 'payroll_run_employees_employee_idx');
        });

        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('line_class', 32);
            $table->string('code', 64);
            $table->string('description');
            $table->foreignId('pay_component_id')->nullable()->constrained('pay_components')->nullOnDelete();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('rate', 19, 8)->nullable();
            $table->decimal('multiplier', 12, 8)->nullable();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('tax_treatment', 48)->nullable();
            $table->string('contribution_treatment', 48)->nullable();
            $table->foreignId('statutory_rule_version_id')->nullable()->constrained('statutory_rule_versions')->restrictOnDelete();
            $table->foreignId('payroll_adjustment_id')->nullable()->constrained('payroll_adjustments')->nullOnDelete();
            $table->foreignId('reverses_line_id')->nullable()->constrained('payroll_run_lines')->restrictOnDelete();
            $table->json('inputs')->nullable();
            $table->timestamps();

            $table->index(['payroll_run_employee_id', 'sequence'], 'payroll_run_lines_payslip_idx');
            $table->index(['payroll_run_id', 'line_class', 'code'], 'payroll_run_lines_run_code_idx');
        });

        Schema::create('payroll_run_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $table->foreignId('payroll_run_employee_id')->nullable()->constrained('payroll_run_employees')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('severity', 16);
            $table->string('code', 64);
            $table->text('message');
            $table->timestamps();

            $table->index(['payroll_run_id', 'severity'], 'payroll_run_exceptions_run_idx');
        });

        Schema::create('payroll_run_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            $table->string('event', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['payroll_run_id', 'id'], 'payroll_run_events_run_idx');
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->foreign('corrects_run_employee_id', 'payroll_adjustments_corrects_fk')
                ->references('id')->on('payroll_run_employees')->nullOnDelete();
            $table->foreign('applied_run_id', 'payroll_adjustments_applied_fk')
                ->references('id')->on('payroll_runs')->nullOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT payroll_runs_type_check CHECK (run_type IN ('regular', 'supplemental', 'reversal'))");
            DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT payroll_runs_status_check CHECK (status IN ('draft', 'calculated', 'reviewed', 'approved', 'finalized', 'cancelled'))");
            DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT payroll_runs_reversal_check CHECK ((run_type = 'reversal') = (reverses_run_id IS NOT NULL))");
            DB::statement("ALTER TABLE payroll_run_employees ADD CONSTRAINT payroll_run_employees_status_check CHECK (status IN ('included', 'excluded'))");
            DB::statement("ALTER TABLE payroll_run_lines ADD CONSTRAINT payroll_run_lines_class_check CHECK (line_class IN ('earning', 'employee_deduction', 'employee_tax', 'employer_contribution', 'informational'))");
            DB::statement("ALTER TABLE payroll_run_exceptions ADD CONSTRAINT payroll_run_exceptions_severity_check CHECK (severity IN ('error', 'warning'))");
        }

        $now = now();
        DB::table('number_sequences')->insertOrIgnore([
            ['key' => 'payroll_run_number', 'name' => 'Payroll run number', 'prefix' => 'PR-{YYYY}-', 'suffix' => '', 'padding' => 5, 'next_number' => 1, 'auto_generate' => true, 'reset_period' => 'yearly', 'current_period' => $now->format('Y'), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'payslip_number', 'name' => 'Payslip number', 'prefix' => 'PS-{YYYY}-', 'suffix' => '', 'padding' => 6, 'next_number' => 1, 'auto_generate' => true, 'reset_period' => 'yearly', 'current_period' => $now->format('Y'), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropForeign('payroll_adjustments_corrects_fk');
            $table->dropForeign('payroll_adjustments_applied_fk');
        });
        Schema::dropIfExists('payroll_run_events');
        Schema::dropIfExists('payroll_run_exceptions');
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
        DB::table('number_sequences')->whereIn('key', ['payroll_run_number', 'payslip_number'])->delete();
    }
};
