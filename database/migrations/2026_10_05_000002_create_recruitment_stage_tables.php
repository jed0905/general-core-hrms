<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment pipeline. recruitment_stages is the configurable template;
 * vacancy_stages is each vacancy's own copy, taken when the vacancy opens, so
 * later template changes never alter an existing vacancy's pipeline.
 *
 * stage_type gives a stage its behaviour: applied | screening | shortlisted now;
 * later phases add interview, assessment, offer, hired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('stage_type', 32);
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'recruitment_stages_active_order_idx');
        });

        Schema::create('vacancy_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            // Traceability only; the copied columns below are what the vacancy uses.
            $table->foreignId('recruitment_stage_id')->nullable()->constrained('recruitment_stages')->nullOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('stage_type', 32);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['vacancy_id', 'sort_order'], 'vacancy_stages_order_unique');
            $table->unique(['vacancy_id', 'code'], 'vacancy_stages_code_unique');
        });

        $now = now();
        DB::table('recruitment_stages')->insert([
            ['code' => 'applied', 'name' => 'Applied', 'stage_type' => 'applied', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'screening', 'name' => 'Screening', 'stage_type' => 'screening', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'shortlisted', 'name' => 'Shortlisted', 'stage_type' => 'shortlisted', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_stages');
        Schema::dropIfExists('recruitment_stages');
    }
};
