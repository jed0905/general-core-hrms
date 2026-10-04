<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Time to fill = days from the vacancy being opened to an offer for it being
 * accepted. One row per accepted offer (one filled opening).
 *
 * Vacancy status "filled" is a manual HR action and openings are reserved at
 * selection, so neither is used as the end event.
 */
class TimeToFillReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'time-to-fill';
    }

    public function title(): string
    {
        return 'Time to Fill';
    }

    public function description(): string
    {
        return 'Days from vacancy opening (and requisition approval) to offer acceptance, for offers accepted in a period.';
    }

    public function notes(): array
    {
        return [
            'Each accepted offer (offer acceptance date in the period) is one filled opening.',
            'Primary measure: vacancy open date → offer accepted. Secondary: requisition approval → offer accepted, only for vacancies created from an approved requisition.',
            'Days are calendar days and include time on hold. Openings filled without an offer are not recorded and can\'t be measured.',
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'accepted_on' => 'Accepted',
            'vacancy_number' => 'Vacancy No.',
            'title' => 'Title',
            'department' => 'Department',
            'opened' => 'Vacancy Opened',
            'days_from_opening' => 'Days from Opening',
            'requisition_approved' => 'Requisition Approved',
            'days_from_requisition' => 'Days from Requisition',
        ];
    }

    public function sortable(): array
    {
        return ['accepted_on' => 'o.responded_at', 'vacancy_number' => 'v.vacancy_number', 'opened' => 'v.opened_at'];
    }

    protected function tiebreaker(): string|array
    {
        return 'o.id';
    }

    public function query(array $filters): Builder
    {
        return $this->inPeriod($this->scopeVacancies(DB::table('job_offers as o')->join('vacancies as v', 'v.id', '=', 'o.vacancy_id'), $filters), 'o.responded_at', $filters)
            ->leftJoin('job_requisitions as r', 'r.id', '=', 'v.job_requisition_id')
            ->where('o.response', 'accepted')
            ->select(['o.id', 'o.responded_at', 'v.vacancy_number', 'v.title', 'v.department_id', 'v.opened_at', 'r.approved_at as requisition_approved_at'])
            ->orderByDesc('o.responded_at')
            ->orderBy('o.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'accepted_on' => substr((string) $row->responded_at, 0, 10),
            'vacancy_number' => $row->vacancy_number,
            'title' => $row->title,
            'department' => $this->tree()->path($row->department_id),
            'opened' => $row->opened_at ? substr((string) $row->opened_at, 0, 10) : null,
            'days_from_opening' => self::days($row->opened_at, $row->responded_at),
            'requisition_approved' => $row->requisition_approved_at ? substr((string) $row->requisition_approved_at, 0, 10) : null,
            'days_from_requisition' => self::days($row->requisition_approved_at, $row->responded_at),
        ];
    }

    public function summary(array $filters): array
    {
        $rows = $this->query($filters)->get();
        $opening = self::dayStats($rows->map(fn ($r) => self::days($r->opened_at, $r->responded_at)));
        $requisition = self::dayStats($rows->map(fn ($r) => self::days($r->requisition_approved_at, $r->responded_at)));

        $byDepartment = $rows->groupBy(fn ($r) => $this->tree()->path($r->department_id) ?? 'Not specified')->sortKeys()
            ->map(function ($g, $department) {
                $s = self::dayStats($g->map(fn ($r) => self::days($r->opened_at, $r->responded_at)));

                return [$department, $g->count(), $s['average'], $s['median']];
            })->values()->all();

        return [
            [
                'title' => 'Time to Fill (days)',
                'columns' => ['Measure', 'Openings measured', 'Average', 'Median', 'Shortest', 'Longest'],
                'rows' => [
                    ['Vacancy opened → offer accepted', $opening['count'], $opening['average'], $opening['median'], $opening['min'], $opening['max']],
                    ['Requisition approved → offer accepted', $requisition['count'], $requisition['average'], $requisition['median'], $requisition['min'], $requisition['max']],
                ],
            ],
            [
                'title' => 'By Department (vacancy opened → offer accepted)',
                'columns' => ['Department', 'Openings filled', 'Average days', 'Median days'],
                'rows' => $byDepartment,
            ],
        ];
    }
}
