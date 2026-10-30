<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated statutory rule versions for any country.
 *
 * - One row per version of (country, jurisdiction, rule_key, variant). Versions of
 *   the same key never overlap (checked in StatutoryRuleService under a lock).
 * - Every version carries its official source, issuance reference and
 *   verification state. Only verified versions may feed a finalized run.
 * - Verified versions are read-only (except closing effective_to / superseding);
 *   a change in law is a new version.
 * - Values live in decimal columns (brackets, parameters), never floats or free text.
 * - Nothing is seeded here: statutory values are entered with their source and
 *   verified through the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('jurisdiction_code', 16)->nullable();
            $table->string('rule_key', 80);
            $table->string('variant', 32)->nullable(); // e.g. a pay frequency for frequency-specific tables
            $table->string('category', 32);
            $table->string('name');
            $table->string('calculation_method', 40);
            $table->string('applies_by', 16)->default('pay_date');
            $table->string('rounding_mode', 16)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('revision')->default(1);
            $table->string('official_source');
            $table->string('source_url', 500);
            $table->string('issuance_reference');
            $table->date('retrieved_at');
            $table->string('verification_status', 16)->default('draft');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['country_code', 'rule_key', 'effective_from'], 'statutory_rule_versions_lookup_idx');
            $table->index(['verification_status'], 'statutory_rule_versions_status_idx');
        });

        Schema::create('statutory_rule_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statutory_rule_version_id')->constrained('statutory_rule_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('range_from', 19, 4);
            $table->decimal('range_to', 19, 4)->nullable(); // null = no upper bound
            $table->decimal('base_amount', 19, 4)->nullable(); // e.g. a salary credit for the range
            $table->decimal('fixed_amount', 19, 4)->nullable();
            $table->decimal('rate', 12, 8)->nullable();
            $table->decimal('excess_over', 19, 4)->nullable();
            $table->decimal('employee_amount', 19, 4)->nullable();
            $table->decimal('employer_amount', 19, 4)->nullable();
            $table->decimal('employee_rate', 12, 8)->nullable();
            $table->decimal('employer_rate', 12, 8)->nullable();
            $table->decimal('employer_extra_amount', 19, 4)->nullable(); // employer-only component, e.g. an insurance fund
            $table->timestamps();

            $table->unique(['statutory_rule_version_id', 'sequence'], 'statutory_rule_brackets_unique');
        });

        Schema::create('statutory_rule_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statutory_rule_version_id')->constrained('statutory_rule_versions')->cascadeOnDelete();
            $table->string('key', 64);
            $table->decimal('value', 24, 8)->nullable();
            $table->string('text_value')->nullable();
            $table->timestamps();

            $table->unique(['statutory_rule_version_id', 'key'], 'statutory_rule_parameters_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE statutory_rule_versions ADD CONSTRAINT statutory_rule_versions_status_check CHECK (verification_status IN ('draft', 'verified', 'superseded', 'rejected'))");
            DB::statement("ALTER TABLE statutory_rule_versions ADD CONSTRAINT statutory_rule_versions_applies_check CHECK (applies_by IN ('pay_date', 'period_start', 'period_end', 'earned_date'))");
            DB::statement('ALTER TABLE statutory_rule_versions ADD CONSTRAINT statutory_rule_versions_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_rule_parameters');
        Schema::dropIfExists('statutory_rule_brackets');
        Schema::dropIfExists('statutory_rule_versions');
    }
};
