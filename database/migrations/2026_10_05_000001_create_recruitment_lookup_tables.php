<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable recruitment lookups (managed under Recruitment → Settings) and
 * the applicant/application number series.
 */
return new class extends Migration
{
    private const SOURCES = [
        ['code' => 'company_website', 'name' => 'Company website'],
        ['code' => 'job_board', 'name' => 'Job board'],
        ['code' => 'referral', 'name' => 'Employee referral'],
        ['code' => 'social_media', 'name' => 'Social media'],
        ['code' => 'agency', 'name' => 'Recruitment agency'],
        ['code' => 'walk_in', 'name' => 'Walk-in'],
        ['code' => 'internal', 'name' => 'Internal candidate'],
        ['code' => 'other', 'name' => 'Other'],
    ];

    private const REJECTION_REASONS = [
        ['code' => 'not_qualified', 'name' => 'Does not meet qualifications'],
        ['code' => 'insufficient_experience', 'name' => 'Insufficient experience'],
        ['code' => 'failed_screening', 'name' => 'Failed screening'],
        ['code' => 'requirements_changed', 'name' => 'Position requirements changed'],
        ['code' => 'position_filled', 'name' => 'Position filled'],
        ['code' => 'other', 'name' => 'Other'],
    ];

    /** [code, name, matching employee_document_types.code used when a hire is converted later] */
    private const DOCUMENT_TYPES = [
        ['resume', 'Resume / CV', 'resume'],
        ['cover_letter', 'Cover letter', 'other'],
        ['certificate', 'Certificate', 'certificate'],
        ['portfolio', 'Portfolio', 'other'],
        ['other', 'Other', 'other'],
    ];

    private const SEQUENCES = [
        ['key' => 'applicant_number', 'name' => 'Applicant number', 'prefix' => 'APP-{YYYY}-'],
        ['key' => 'application_number', 'name' => 'Application number', 'prefix' => 'APL-{YYYY}-'],
    ];

    public function up(): void
    {
        Schema::create('recruitment_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('applicant_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            // Where the file goes in Employee Documents if the applicant is hired (later phase).
            $table->foreignId('employee_document_type_id')->nullable()->constrained('employee_document_types')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach ([['recruitment_sources', self::SOURCES], ['rejection_reasons', self::REJECTION_REASONS]] as [$table, $rows]) {
            DB::table($table)->insert(array_map(
                fn ($row, $i) => $row + ['is_active' => true, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now],
                $rows,
                array_keys($rows)
            ));
        }

        $employeeTypes = DB::table('employee_document_types')->pluck('id', 'code');
        DB::table('applicant_document_types')->insert(array_map(fn ($t) => [
            'code' => $t[0],
            'name' => $t[1],
            'employee_document_type_id' => $employeeTypes[$t[2]] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::DOCUMENT_TYPES));

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
        Schema::dropIfExists('applicant_document_types');
        Schema::dropIfExists('rejection_reasons');
        Schema::dropIfExists('recruitment_sources');
    }
};
