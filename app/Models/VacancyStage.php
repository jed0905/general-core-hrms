<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vacancy's own copy of a pipeline stage (snapshot of recruitment_stages).
 */
class VacancyStage extends Model
{
    protected $fillable = [
        'vacancy_id',
        'recruitment_stage_id',
        'code',
        'name',
        'stage_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'vacancy_id' => 'integer',
            'recruitment_stage_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'current_vacancy_stage_id');
    }
}
