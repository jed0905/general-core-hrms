<?php

namespace App\Services\Recruitment;

use App\Models\RecruitmentStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recruitment lookups managed by HR: sources, rejection reasons, interview
 * types, assessment types, evaluation criteria and stage names. Codes are
 * generated once and never change (reports and history rely on them). Stage
 * edits only affect vacancies opened afterwards; assessments copy their type's
 * result type and scorecards copy criterion names, so lookup edits never change
 * recorded results.
 */
class RecruitmentSettingsService
{
    /** Optional lookup columns, written only where the model has them. */
    private const OPTIONAL = ['description', 'result_type'];

    /** @param class-string<Model> $model */
    public function createLookup(string $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data) {
            $base = Str::slug($data['name'], '_') ?: 'item';
            $code = $base;
            for ($i = 2; $model::where('code', $code)->exists(); $i++) {
                $code = "{$base}_{$i}";
            }

            return $model::create([
                'code' => $code,
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? ((int) $model::max('sort_order') + 10),
            ] + $this->optional($model, $data));
        });
    }

    public function updateLookup(Model $lookup, array $data): Model
    {
        $lookup->update(Arr::only($data, ['name', 'is_active', 'sort_order']) + $this->optional($lookup::class, $data));

        return $lookup;
    }

    /** @param class-string<Model> $model */
    protected function optional(string $model, array $data): array
    {
        return Arr::only($data, array_intersect(self::OPTIONAL, (new $model)->getFillable()));
    }

    /**
     * Rename a template stage (its type and order are fixed in this phase).
     */
    public function renameStage(RecruitmentStage $stage, string $name): RecruitmentStage
    {
        $stage->update(['name' => $name]);

        return $stage;
    }
}
