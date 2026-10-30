<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pay components, salary structures and effective-dated employee compensation.
 *
 * - pay_components: what can appear on a payslip. line_class decides how it
 *   affects net pay (employer contributions never do).
 * - pay_component_treatments: per country, the localization-defined tax and
 *   contribution treatment keys (the core never interprets them).
 * - salary_structures / salary_structure_grades: ranges per existing pay_grades,
 *   in decimal (pay_grade_currencies' double columns are not reused).
 * - employee_compensations: salary history. Records never overlap per assignment;
 *   a change closes the previous record. Approved records used by a finalized run
 *   are immutable (enforced in the model and service).
 * - employee_pay_components: recurring allowances/deductions per assignment.
 */
return new class extends Migration
{
    private const BASES = "'annual', 'monthly', 'semi_monthly', 'biweekly', 'weekly', 'daily', 'hourly'";

    public function up(): void
    {
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('line_class', 32);
            $table->string('category', 32);
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->restrictOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['line_class', 'is_active'], 'pay_components_class_idx');
        });

        Schema::create('pay_component_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_component_id')->constrained('pay_components')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('tax_treatment', 48);
            $table->string('contribution_treatment', 48);
            $table->timestamps();

            $table->unique(['pay_component_id', 'country_code'], 'pay_component_treatments_unique');
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->restrictOnDelete();
            $table->string('name');
            $table->char('currency', 3);
            $table->string('basis', 16);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_structure_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained('salary_structures')->cascadeOnDelete();
            $table->foreignId('pay_grade_id')->constrained('pay_grades')->restrictOnDelete();
            $table->decimal('minimum_amount', 19, 4);
            $table->decimal('midpoint_amount', 19, 4)->nullable();
            $table->decimal('maximum_amount', 19, 4);
            $table->timestamps();

            $table->unique(['salary_structure_id', 'pay_grade_id'], 'salary_structure_grades_unique');
        });

        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employment_assignment_id')->constrained('employment_assignments')->restrictOnDelete();
            $table->string('basis', 16);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('pay_grade_id')->nullable()->constrained('pay_grades')->nullOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['employment_assignment_id', 'effective_from'], 'employee_compensations_assignment_idx');
            $table->index(['employee_id', 'effective_from'], 'employee_compensations_employee_idx');
        });

        Schema::create('employee_pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employment_assignment_id')->constrained('employment_assignments')->restrictOnDelete();
            $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('amount_basis', 16)->default('per_period'); // per_period | monthly
            $table->decimal('total_limit', 19, 4)->nullable(); // e.g. a loan principal; stops once reached
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employment_assignment_id', 'effective_from'], 'employee_pay_components_assignment_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE pay_components ADD CONSTRAINT pay_components_class_check CHECK (line_class IN ('earning', 'employee_deduction', 'employer_contribution', 'informational'))");
            DB::statement('ALTER TABLE salary_structures ADD CONSTRAINT salary_structures_basis_check CHECK (basis IN ('.self::BASES.'))');
            DB::statement('ALTER TABLE salary_structure_grades ADD CONSTRAINT salary_structure_grades_range_check CHECK (minimum_amount >= 0 AND maximum_amount >= minimum_amount)');
            DB::statement('ALTER TABLE employee_compensations ADD CONSTRAINT employee_compensations_basis_check CHECK (basis IN ('.self::BASES.'))');
            DB::statement("ALTER TABLE employee_compensations ADD CONSTRAINT employee_compensations_status_check CHECK (status IN ('pending', 'approved'))");
            DB::statement('ALTER TABLE employee_compensations ADD CONSTRAINT employee_compensations_amount_check CHECK (amount >= 0)');
            DB::statement('ALTER TABLE employee_compensations ADD CONSTRAINT employee_compensations_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
            DB::statement("ALTER TABLE employee_pay_components ADD CONSTRAINT employee_pay_components_basis_check CHECK (amount_basis IN ('per_period', 'monthly'))");
        }

        // Core components the engine itself emits. Their treatment per country is configured, not assumed.
        $now = now();
        DB::table('pay_components')->insertOrIgnore([
            ['code' => 'BASIC', 'name' => 'Basic pay', 'line_class' => 'earning', 'category' => 'basic', 'priority' => 10, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PAID_LEAVE', 'name' => 'Paid leave', 'line_class' => 'earning', 'category' => 'basic', 'priority' => 11, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ABSENCE', 'name' => 'Absences', 'line_class' => 'earning', 'category' => 'attendance', 'priority' => 20, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LWOP', 'name' => 'Leave without pay', 'line_class' => 'earning', 'category' => 'attendance', 'priority' => 21, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LATE', 'name' => 'Tardiness', 'line_class' => 'earning', 'category' => 'attendance', 'priority' => 22, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'UNDERTIME', 'name' => 'Undertime', 'line_class' => 'earning', 'category' => 'attendance', 'priority' => 23, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PREMIUM', 'name' => 'Premium time', 'line_class' => 'earning', 'category' => 'premium', 'priority' => 30, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pay_components');
        Schema::dropIfExists('employee_compensations');
        Schema::dropIfExists('salary_structure_grades');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('pay_component_treatments');
        Schema::dropIfExists('pay_components');
    }
};
