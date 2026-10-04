<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Vacancies that existed during the period (created on or before its end and
 * not closed before its start), with requisition activity in the period.
 */
class VacancyReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-vacancies';
    }

    public function title(): string
    {
        return 'Vacancies & Requisitions';
    }

    public function description(): string
    {
        return 'Vacancies active in a period with openings, applications, offers and hires, plus requisition activity.';
    }

    protected function filterKeys(): array
    {
        return [...parent::filterKeys(), 'vacancy_status'];
    }

    public function notes(): array
    {
        return [
            'A vacancy is listed when it was created on or before the end of the period and was still open (no close date, or closed on/after the start).',
            '"Reserved" is the number of openings currently held by selected candidates (vacancies.filled_count); "Hires" counts converted applications.',
            'Only the current vacancy status is stored; past statuses can\'t be reconstructed.',
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'vacancy_number' => 'Vacancy No.',
            'title' => 'Title',
            'department' => 'Department',
            'hiring_manager' => 'Hiring Manager',
            'status' => 'Status',
            'opened' => 'Opened',
            'closing_date' => 'Closing Date',
            'openings' => 'Openings',
            'reserved' => 'Reserved',
            'applications' => 'Applications (all)',
            'applications_in_period' => 'Applications (period)',
            'offers_accepted' => 'Offers Accepted',
            'hires' => 'Hires',
        ];
    }

    public function sortable(): array
    {
        return ['vacancy_number' => 'v.vacancy_number', 'title' => 'v.title', 'status' => 'v.status', 'opened' => 'v.opened_at', 'openings' => 'v.openings'];
    }

    protected function tiebreaker(): string|array
    {
        return 'v.id';
    }

    protected function vacancies(array $filters): Builder
    {
        return $this->scopeVacancies(DB::table('vacancies as v'), $filters)
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('v.created_at', '<=', $filters['date_to']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->where(fn ($q) => $q->whereNull('v.closed_at')->orWhereDate('v.closed_at', '>=', $filters['date_from'])));
    }

    public function query(array $filters): Builder
    {
        $count = fn (string $table, ?callable $extra = null) => function ($q) use ($table, $extra) {
            $q->from($table)->whereColumn("{$table}.vacancy_id", 'v.id')->selectRaw('count(*)');
            $extra && $extra($q);
        };

        return $this->vacancies($filters)
            ->leftJoin('employees as hm', 'hm.id', '=', 'v.hiring_manager_id')
            ->select(['hm.emp_last_name as hm_last', 'hm.emp_first_name as hm_first', 'v.id', 'v.vacancy_number', 'v.title', 'v.department_id', 'v.hiring_manager_id', 'v.status', 'v.opened_at', 'v.closing_date', 'v.openings', 'v.filled_count'])
            ->selectSub($count('applications'), 'applications')
            ->selectSub($count('applications', fn ($q) => $this->inPeriod($q, 'applications.applied_at', $filters)), 'applications_in_period')
            ->selectSub($count('job_offers', fn ($q) => $q->where('job_offers.response', 'accepted')), 'offers_accepted')
            ->selectSub(fn ($q) => $q->from('application_conversions as c')->join('applications as ca', 'ca.id', '=', 'c.application_id')
                ->whereColumn('ca.vacancy_id', 'v.id')->selectRaw('count(*)'), 'hires')
            ->orderByDesc('v.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'vacancy_number' => $row->vacancy_number,
            'title' => $row->title,
            'department' => $this->tree()->path($row->department_id),
            'hiring_manager' => self::personName($row->hm_last, $row->hm_first),
            'status' => ucwords(str_replace('_', ' ', $row->status)),
            'opened' => $row->opened_at ? substr((string) $row->opened_at, 0, 10) : null,
            'closing_date' => $row->closing_date ? substr((string) $row->closing_date, 0, 10) : null,
            'openings' => (int) $row->openings,
            'reserved' => (int) $row->filled_count,
            'applications' => (int) $row->applications,
            'applications_in_period' => (int) $row->applications_in_period,
            'offers_accepted' => (int) $row->offers_accepted,
            'hires' => (int) $row->hires,
        ];
    }

    public function summary(array $filters): array
    {
        $byStatus = $this->vacancies($filters)->groupBy('v.status')
            ->select('v.status', DB::raw('count(*) as c'), DB::raw('coalesce(sum(v.openings), 0) as openings'))->get();

        $requisitions = DB::table('job_requisitions as r')
            ->when(! empty($filters['department_id']), function ($q) use ($filters) {
                $ids = filter_var($filters['include_sub_departments'] ?? true, FILTER_VALIDATE_BOOLEAN)
                    ? $this->tree()->withDescendants((int) $filters['department_id'])
                    : [(int) $filters['department_id']];
                $q->whereIn('r.department_id', $ids);
            })
            ->when(! empty($filters['vacancy_id']), fn ($q) => $q->whereIn('r.id', DB::table('vacancies')->where('id', $filters['vacancy_id'])->select('job_requisition_id')))
            ->when(! empty($filters['hiring_manager_id']), fn ($q) => $q->whereIn('r.id', DB::table('vacancies')->where('hiring_manager_id', $filters['hiring_manager_id'])->select('job_requisition_id')));

        $event = fn (string $column) => $this->inPeriod(clone $requisitions, "r.{$column}", $filters);

        return [
            [
                'title' => 'Vacancies by Current Status',
                'columns' => ['Status', 'Vacancies', 'Openings'],
                'rows' => $byStatus->map(fn ($s) => [ucwords(str_replace('_', ' ', $s->status)), (int) $s->c, (int) $s->openings])->values()->all(),
            ],
            [
                'title' => 'Requisition Activity in the Period',
                'columns' => ['Event', 'Requisitions', 'Positions'],
                'rows' => collect(['created_at' => 'Created', 'submitted_at' => 'Submitted for approval', 'approved_at' => 'Approved', 'rejected_at' => 'Rejected', 'cancelled_at' => 'Cancelled'])
                    ->map(fn ($label, $column) => [$label, (clone $event($column))->count(), (int) (clone $event($column))->sum('r.positions')])
                    ->values()->all(),
            ],
            [
                'title' => 'Approved Requisitions Without a Vacancy (today)',
                'columns' => ['Requisitions', 'Positions'],
                'rows' => [[
                    (clone $requisitions)->where('r.status', 'approved')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('vacancies')->whereColumn('vacancies.job_requisition_id', 'r.id'))->count(),
                    (int) (clone $requisitions)->where('r.status', 'approved')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('vacancies')->whereColumn('vacancies.job_requisition_id', 'r.id'))->sum('r.positions'),
                ]],
            ],
        ];
    }
}
