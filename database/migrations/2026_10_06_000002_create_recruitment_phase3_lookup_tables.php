<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable interview types, assessment types and evaluation criteria
 * (Recruitment → Settings). Names are examples; no behaviour depends on them.
 */
return new class extends Migration
{
    private const INTERVIEW_TYPES = [
        ['code' => 'hr_interview', 'name' => 'HR Interview'],
        ['code' => 'initial_interview', 'name' => 'Initial Interview'],
        ['code' => 'technical_interview', 'name' => 'Technical Interview'],
        ['code' => 'panel_interview', 'name' => 'Panel Interview'],
        ['code' => 'final_interview', 'name' => 'Final Interview'],
    ];

    /** result_type: score (score out of a maximum) or pass_fail. */
    private const ASSESSMENT_TYPES = [
        ['code' => 'technical_assessment', 'name' => 'Technical Assessment', 'result_type' => 'score'],
        ['code' => 'skills_assessment', 'name' => 'Skills Assessment', 'result_type' => 'score'],
        ['code' => 'written_assessment', 'name' => 'Written Assessment', 'result_type' => 'score'],
        ['code' => 'practical_assessment', 'name' => 'Practical Assessment', 'result_type' => 'pass_fail'],
        ['code' => 'aptitude_assessment', 'name' => 'Aptitude Assessment', 'result_type' => 'score'],
    ];

    private const CRITERIA = [
        ['code' => 'technical_knowledge', 'name' => 'Technical Knowledge'],
        ['code' => 'communication', 'name' => 'Communication'],
        ['code' => 'problem_solving', 'name' => 'Problem Solving'],
        ['code' => 'teamwork', 'name' => 'Teamwork'],
        ['code' => 'leadership', 'name' => 'Leadership'],
        ['code' => 'relevant_experience', 'name' => 'Relevant Experience'],
    ];

    public function up(): void
    {
        Schema::create('interview_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('result_type', 16)->default('score');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE assessment_types ADD CONSTRAINT assessment_types_result_type_check CHECK (result_type IN ('score', 'pass_fail'))");
        }

        $now = now();
        foreach ([['interview_types', self::INTERVIEW_TYPES], ['assessment_types', self::ASSESSMENT_TYPES], ['evaluation_criteria', self::CRITERIA]] as [$table, $rows]) {
            DB::table($table)->insert(array_map(
                fn ($row, $i) => $row + ['is_active' => true, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now],
                $rows,
                array_keys($rows)
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('assessment_types');
        Schema::dropIfExists('interview_types');
    }
};
