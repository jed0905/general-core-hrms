<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who employs and pays whom, and under which country's payroll law.
 *
 * - legal_entities: the registered employers. Each has its own country (ISO 3166-1
 *   alpha-2), functional currency, timezone and locale; the app timezone is not a
 *   payroll default. localization_settings holds company policy the country
 *   localization reads (never statutory values).
 * - pay_groups: employees of one entity sharing a frequency, pay currency and
 *   working-time basis. payroll_country_code selects the localization.
 * - employment_assignments: employee x entity x pay group x work location x dates.
 *   payroll_country_code is the resolved jurisdiction; overriding it needs a reason.
 * - employee_tax_residencies / employee_statutory_profiles: effective-dated,
 *   evidenced, never derived from nationality. Profile fields are encrypted.
 * - payroll_entity_user: which legal entities a payroll administrator may see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('legal_name');
            $table->char('country_code', 2);
            $table->string('subdivision_code', 10)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_identifier', 100)->nullable();
            $table->char('functional_currency', 3);
            $table->string('timezone', 64);
            $table->string('locale', 16);
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->string('address')->nullable();
            $table->json('localization_settings')->nullable();
            $table->foreignId('organization_general_information_id')->nullable()
                ->constrained('organization_general_information')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country_code', 'is_active'], 'legal_entities_country_idx');
        });

        Schema::create('pay_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->char('payroll_country_code', 2);
            $table->string('pay_frequency', 16);
            $table->char('pay_currency', 3);
            $table->decimal('hours_per_day', 6, 4);
            $table->decimal('annual_working_days', 8, 4);
            $table->string('proration_method', 16)->default('calendar_days');
            $table->string('rounding_mode', 16)->default('half_up');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['legal_entity_id', 'is_active'], 'pay_groups_entity_idx');
        });

        Schema::create('employment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->restrictOnDelete();
            $table->foreignId('pay_group_id')->constrained('pay_groups')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->char('work_country_code', 2);
            $table->string('work_subdivision_code', 10)->nullable();
            $table->char('payroll_country_code', 2);
            $table->string('jurisdiction_override_reason')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from'], 'employment_assignments_employee_idx');
            $table->index(['pay_group_id', 'effective_from'], 'employment_assignments_group_idx');
        });

        Schema::create('employee_tax_residencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->char('country_code', 2);
            $table->string('residency_status', 32); // resident | non_resident | other (localization may refine)
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from'], 'employee_tax_residencies_employee_idx');
        });

        Schema::create('employee_statutory_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->char('country_code', 2);
            $table->text('fields'); // encrypted json; fields are declared by the localization
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'country_code', 'effective_from'], 'employee_statutory_profiles_idx');
        });

        Schema::create('payroll_entity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'legal_entity_id'], 'payroll_entity_user_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE pay_groups ADD CONSTRAINT pay_groups_frequency_check CHECK (pay_frequency IN ('weekly', 'biweekly', 'semi_monthly', 'monthly'))");
            DB::statement("ALTER TABLE pay_groups ADD CONSTRAINT pay_groups_proration_check CHECK (proration_method IN ('calendar_days', 'working_days'))");
            DB::statement("ALTER TABLE pay_groups ADD CONSTRAINT pay_groups_rounding_check CHECK (rounding_mode IN ('half_up', 'half_even', 'up', 'down'))");
            DB::statement('ALTER TABLE pay_groups ADD CONSTRAINT pay_groups_basis_check CHECK (hours_per_day > 0 AND annual_working_days > 0)');
            DB::statement('ALTER TABLE employment_assignments ADD CONSTRAINT employment_assignments_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
            DB::statement('ALTER TABLE employee_tax_residencies ADD CONSTRAINT employee_tax_residencies_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entity_user');
        Schema::dropIfExists('employee_statutory_profiles');
        Schema::dropIfExists('employee_tax_residencies');
        Schema::dropIfExists('employment_assignments');
        Schema::dropIfExists('pay_groups');
        Schema::dropIfExists('legal_entities');
    }
};
