<?php

namespace App\Services\Recruitment;

use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Copies the configured stage template onto a vacancy (vacancy_stages). Done
 * once, when the vacancy first opens; later template changes never touch it.
 */
class VacancyStageService
{
    /**
     * Idempotent: a vacancy that already has stages keeps them. Call inside the
     * caller's transaction with the vacancy row locked.
     *
     * @return Collection<int, VacancyStage>
     */
    public function snapshot(Vacancy $vacancy): Collection
    {
        if ($vacancy->stages()->exists()) {
            return $vacancy->stages()->get();
        }

        $template = RecruitmentStage::where('is_active', true)
            ->whereIn('stage_type', RecruitmentStage::TYPES)
            ->orderBy('sort_order')
            ->get();

        if ($template->isEmpty() || $template->first()->stage_type !== RecruitmentStage::TYPE_APPLIED) {
            throw ValidationException::withMessages(['status' => ['The recruitment pipeline must start with an active "applied" stage. Check Recruitment → Settings.']]);
        }

        foreach ($template as $stage) {
            $vacancy->stages()->create([
                'recruitment_stage_id' => $stage->id,
                'code' => $stage->code,
                'name' => $stage->name,
                'stage_type' => $stage->stage_type,
                'sort_order' => $stage->sort_order,
            ]);
        }

        return $vacancy->stages()->get();
    }
}
