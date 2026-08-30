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
        Schema::create('leave_policy_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_policy_id')
                ->constrained('leave_policies')
                ->cascadeOnDelete();

            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->restrictOnDelete();

            /*
             * How the employee earns/gains leave credits.
             *
             * fixed       = company grants a fixed amount
             * monthly     = credits are earned monthly
             * annually    = credits are granted annually
             * per_payroll = credits are earned per payroll period
             * none        = no automatic accrual
             */
            $table->enum('accrual_method', [
                'fixed',
                'monthly',
                'annually',
                'per_payroll',
                'none',
            ])->default('none');

            /*
             * Amount of leave credits earned per accrual period.
             *
             * Example:
             * 1.25 days per month
             */
            $table->decimal('accrual_rate', 8, 4)->nullable();

            /*
             * How often the accrual is granted.
             */
            $table->enum('grant_frequency', [
                'monthly',
                'quarterly',
                'annually',
                'per_payroll',
                'none',
            ])->default('none');

            /*
             * Maximum number of leave days/hours that
             * can accumulate.
             *
             * NULL = unlimited.
             */
            $table->decimal('maximum_balance', 8, 4)->nullable();

            /*
             * Maximum amount that can be carried over
             * to the next leave year.
             *
             * NULL = no carry-forward limit.
             */
            $table->decimal('carry_forward_limit', 8, 4)->nullable();

            /*
             * Minimum number of months an employee must
             * have worked before becoming entitled.
             *
             * Example:
             * 6 = employee becomes eligible after 6 months.
             */
            $table->unsignedSmallInteger('minimum_service_months')->default(0);

            /*
             * Number of days/hours that must pass before
             * the employee can use the leave.
             */
            $table->unsignedSmallInteger('waiting_period')->default(0);

            /*
             * Whether the employee can file/use leave
             * even when the available balance is insufficient.
             */
            $table->boolean('allow_negative')->default(false);

            /*
             * Whether this leave requires approval.
             */
            $table->boolean('requires_approval')->default(true);

            /*
             * Whether supporting documents are required.
             */
            $table->boolean('requires_attachment')->default(false);

            /*
             * Whether the employee can file a half-day leave.
             */
            $table->boolean('allows_half_day')->default(true);

            /*
             * Whether the employee can file leave by hours.
             */
            $table->boolean('allows_hourly')->default(false);

            /*
             * Whether unused credits expire at the end
             * of the leave year.
             */
            $table->boolean('expires')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'leave_policy_id',
                'leave_type_id',
            ]);

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policy_rules');
    }
};
