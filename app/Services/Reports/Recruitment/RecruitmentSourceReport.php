<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Applications received in the period by recruitment source, and how far
 * each source's applications progressed (cohort, distinct applications).
 */
class RecruitmentSourceReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-sources';
    }

    public function title(): string
    {
        return 'Application Sources';
    }

    public function description(): string
    {
        return 'Applications received in a period by source, with shortlist, interview, offer and hire outcomes.';
    }

    public function notes(): array
    {
        return [
            'Cohort: applications received (applied date) in the period, grouped by the source recorded on the application. Applications without a source are shown as "Not specified".',
            'Outcomes count applications that ever reached the step, even if it happened after the period.',
        ];
    }

    public function summary(array $filters): array
    {
        $base = fn () => $this->inPeriod($this->applicationBase($filters), 'a.applied_at', $filters);
        $grouped = fn (Builder $q) => $q->groupBy('a.recruitment_source_id')
            ->select('a.recruitment_source_id', DB::raw('count(distinct a.id) as c'))
            ->pluck('c', 'recruitment_source_id')->mapWithKeys(fn ($c, $id) => [(string) $id => (int) $c]);

        $applied = $grouped($base());
        $metrics = [
            'shortlisted' => $grouped($base()->whereExists($this->reachedStage('shortlisted'))),
            'interviewed' => $grouped($base()->whereExists($this->reachedStage('interview'))),
            'offered' => $grouped($base()->whereExists($this->hasIssuedOffer())),
            'accepted' => $grouped($base()->whereExists($this->hasAcceptedOffer())),
            'hired' => $grouped($base()->whereExists($this->hasConversion())),
        ];
        $names = DB::table('recruitment_sources')->pluck('name', 'id');
        $total = $applied->sum();

        $rows = $applied->sortDesc()->map(function ($count, $id) use ($metrics, $names, $total) {
            $m = collect($metrics)->map(fn ($values) => $values[$id] ?? 0);

            return [
                $id === '' ? 'Not specified' : self::label($names[$id] ?? null),
                $count,
                self::pct($count, $total),
                $m['shortlisted'],
                $m['interviewed'],
                $m['offered'],
                $m['accepted'],
                $m['hired'],
                self::pct($m['hired'], $count),
            ];
        })->values()->all();

        return [[
            'title' => 'Applications by Source (received in the period)',
            'columns' => ['Source', 'Applications', '% of all', 'Shortlisted', 'Interviewed', 'Offer issued', 'Offer accepted', 'Hired', 'Hire rate'],
            'rows' => $rows,
        ]];
    }

    protected function filterKeys(): array
    {
        return [...parent::filterKeys(), 'recruitment_source_id'];
    }
}
