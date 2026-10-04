<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional yearly counter reset (REQ-2026-00001 ... REQ-2027-00001) and the
 * recruitment number series. The employee-number sequence keeps no reset.
 */
return new class extends Migration
{
    private const SEQUENCES = [
        ['key' => 'job_requisition_number', 'name' => 'Job requisition number', 'prefix' => 'REQ-{YYYY}-'],
        ['key' => 'vacancy_number', 'name' => 'Vacancy number', 'prefix' => 'VAC-{YYYY}-'],
    ];

    public function up(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            // null = never reset; "yearly" = counter restarts at 1 each calendar year.
            $table->string('reset_period', 16)->nullable()->after('auto_generate');
            // The period (e.g. "2026") the current counter belongs to.
            $table->string('current_period', 16)->nullable()->after('reset_period');
        });

        $now = now();
        foreach (self::SEQUENCES as $sequence) {
            DB::table('number_sequences')->insertOrIgnore($sequence + [
                'suffix' => '',
                'padding' => 5,
                'next_number' => 1,
                'auto_generate' => true,
                'reset_period' => 'yearly',
                'current_period' => $now->format('Y'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('number_sequences')->whereIn('key', array_column(self::SEQUENCES, 'key'))->delete();

        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropColumn(['reset_period', 'current_period']);
        });
    }
};
