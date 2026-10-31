<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns for attendance integration (existing behavior unchanged):
 *
 * - holidays.country_code / subdivision_code: null = applies everywhere (today's
 *   behavior); set them for country- or region-specific holidays.
 * - pay_groups.time_source: where payroll reads time quantities.
 *   "manual_inputs" (default, current behavior) or "attendance" (approved
 *   attendance period summaries).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('type');
            $table->string('subdivision_code', 10)->nullable()->after('country_code');
            $table->index(['date', 'country_code'], 'holidays_date_country_idx');
        });

        Schema::table('pay_groups', function (Blueprint $table) {
            $table->string('time_source', 16)->default('manual_inputs')->after('rounding_mode');
        });
    }

    public function down(): void
    {
        Schema::table('pay_groups', function (Blueprint $table) {
            $table->dropColumn('time_source');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex('holidays_date_country_idx');
            $table->dropColumn(['country_code', 'subdivision_code']);
        });
    }
};
