<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll calendars and their periods, one calendar per pay group per year.
 *
 * - Periods never overlap within a pay group (unique start date + service check).
 * - pay_date is a date in the legal entity's timezone; tax_year is the year the
 *   localization assigns the period to (not assumed to be the calendar year).
 * - A closed period accepts no new regular run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_group_id')->constrained('pay_groups')->restrictOnDelete();
            $table->unsignedSmallInteger('calendar_year');
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pay_group_id', 'calendar_year'], 'payroll_calendars_group_year_unique');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_calendar_id')->constrained('payroll_calendars')->restrictOnDelete();
            $table->foreignId('pay_group_id')->constrained('pay_groups')->restrictOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('cutoff_date')->nullable();
            $table->date('pay_date');
            $table->unsignedSmallInteger('tax_year');
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->unique(['pay_group_id', 'period_start'], 'payroll_periods_group_start_unique');
            $table->unique(['payroll_calendar_id', 'period_number'], 'payroll_periods_calendar_number_unique');
            $table->index(['pay_group_id', 'pay_date'], 'payroll_periods_group_paydate_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_dates_check CHECK (period_end >= period_start)');
            DB::statement("ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_status_check CHECK (status IN ('open', 'closed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_calendars');
    }
};
