<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Screening outcomes recorded in the period (by screened_on). Aggregate only:
 * no remarks and no candidate details.
 */
class ScreeningReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-screening';
    }

    public function title(): string
    {
        return 'Screening';
    }

    public function description(): string
    {
        return 'Screening results in a period, pass rate, failure reasons and applications awaiting screening.';
    }

    protected function filterKeys(): array
    {
        return [...parent::filterKeys(), 'recruitment_source_id'];
    }

    public function notes(): array
    {
        return [
            'Screenings are counted by their screening date (screened_on). Each application has at most one screening record, holding its latest result.',
            '"Awaiting screening" is as of today: applications currently in a Screening stage with no result recorded.',
        ];
    }

    public function summary(array $filters): array
    {
        $screened = fn () => $this->inPeriod($this->applicationBase($filters)->join('application_screenings as sc', 'sc.application_id', '=', 'a.id'), 'sc.screened_on', $filters);

        $results = $screened()->groupBy('sc.result')->select('sc.result', DB::raw('count(distinct a.id) as c'))->pluck('c', 'result');
        $passed = (int) ($results['passed'] ?? 0);
        $failed = (int) ($results['failed'] ?? 0);

        $reasons = $screened()->where('sc.result', 'failed')
            ->leftJoin('rejection_reasons as rr', 'rr.id', '=', 'sc.rejection_reason_id')
            ->groupBy('rr.name')->select('rr.name', DB::raw('count(distinct a.id) as c'))->orderByDesc('c')->get()
            ->map(fn ($r) => ['label' => self::label($r->name), 'count' => (int) $r->c]);

        $byVacancy = $screened()->groupBy('v.id', 'v.vacancy_number', 'v.title')
            ->select('v.vacancy_number', 'v.title',
                DB::raw("sum(case when sc.result = 'passed' then 1 else 0 end) as passed"),
                DB::raw("sum(case when sc.result = 'failed' then 1 else 0 end) as failed"))
            ->orderBy('v.vacancy_number')->get();

        $awaiting = $this->applicationBase($filters)
            ->join('vacancy_stages as cs', 'cs.id', '=', 'a.current_vacancy_stage_id')
            ->where('cs.stage_type', 'screening')
            ->whereIn('a.status', ['active', 'shortlisted'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('application_screenings as x')->whereColumn('x.application_id', 'a.id'));

        return [
            [
                'title' => 'Screening Results (screened in the period)',
                'columns' => ['Screened', 'Passed', 'Failed', 'Pass rate'],
                'rows' => [[$passed + $failed, $passed, $failed, self::pct($passed, $passed + $failed)]],
            ],
            $this->shareSection('Failed Screening by Reason', 'Reason', $reasons, 'Applications'),
            [
                'title' => 'Screening by Vacancy',
                'columns' => ['Vacancy', 'Passed', 'Failed', 'Pass rate'],
                'rows' => $byVacancy->map(fn ($v) => ["{$v->vacancy_number} · {$v->title}", (int) $v->passed, (int) $v->failed, self::pct((int) $v->passed, (int) $v->passed + (int) $v->failed)])->all(),
            ],
            [
                'title' => 'Awaiting Screening (today)',
                'columns' => ['Applications'],
                'rows' => [[$this->countDistinct($awaiting, 'a.id')]],
            ],
        ];
    }
}
