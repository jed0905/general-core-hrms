<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recruitment phase 3: Interview (40), Assessment (50) and Evaluation (60) in the
 * stage template. Only the template changes; vacancies that already opened keep
 * their own snapshot (vacancy_stages) and never gain these stages.
 */
return new class extends Migration
{
    private const STAGES = [
        ['code' => 'interview', 'name' => 'Interview', 'stage_type' => 'interview', 'sort_order' => 40],
        ['code' => 'assessment', 'name' => 'Assessment', 'stage_type' => 'assessment', 'sort_order' => 50],
        ['code' => 'evaluation', 'name' => 'Evaluation', 'stage_type' => 'evaluation', 'sort_order' => 60],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::STAGES as $stage) {
            DB::table('recruitment_stages')->insertOrIgnore($stage + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // vacancy_stages.recruitment_stage_id is nullOnDelete; existing snapshots keep their copied columns.
        DB::table('recruitment_stages')->whereIn('code', array_column(self::STAGES, 'code'))->delete();
    }
};
