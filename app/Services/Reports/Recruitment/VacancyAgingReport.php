<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Vacancies open or on hold today, aged from the day they were opened.
 */
class VacancyAgingReport extends RecruitmentReport
{
    public const BUCKETS = ['0–30 days' => [0, 30], '31–60 days' => [31, 60], '61–90 days' => [61, 90], 'Over 90 days' => [91, PHP_INT_MAX]];

    public static function key(): string
    {
        return 'vacancy-aging';
    }

    public function title(): string
    {
        return 'Vacancy Aging';
    }

    public function description(): string
    {
        return 'How long open and on-hold vacancies have been open, as of today.';
    }

    protected function filterKeys(): array
    {
        return ['department_id', 'include_sub_departments', 'hiring_manager_id'];
    }

    public function notes(): array
    {
        return [
            'As of today; there is no date range. Age = days since the vacancy was first opened (vacancies.opened_at), including any time on hold.',
            'Vacancies with no recorded open date are counted as "Not recorded".',
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'vacancy_number' => 'Vacancy No.',
            'title' => 'Title',
            'department' => 'Department',
            'status' => 'Status',
            'opened' => 'Opened',
            'age_days' => 'Days Open',
            'closing_date' => 'Closing Date',
            'past_closing' => 'Past Closing Date',
            'open_positions' => 'Unreserved Openings',
            'active_applications' => 'Active Applications',
        ];
    }

    public function sortable(): array
    {
        return ['vacancy_number' => 'v.vacancy_number', 'opened' => 'v.opened_at', 'age_days' => 'v.opened_at', 'closing_date' => 'v.closing_date'];
    }

    protected function tiebreaker(): string|array
    {
        return 'v.id';
    }

    protected function vacancies(array $filters): Builder
    {
        return $this->scopeVacancies(DB::table('vacancies as v'), $filters)->whereIn('v.status', ['open', 'on_hold']);
    }

    public function query(array $filters): Builder
    {
        return $this->vacancies($filters)
            ->select(['v.id', 'v.vacancy_number', 'v.title', 'v.department_id', 'v.status', 'v.opened_at', 'v.closing_date', 'v.openings', 'v.filled_count'])
            ->selectSub(fn ($q) => $q->from('applications as a')->whereColumn('a.vacancy_id', 'v.id')->whereIn('a.status', ['active', 'shortlisted'])->selectRaw('count(*)'), 'active_applications')
            ->orderBy('v.opened_at')
            ->orderBy('v.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        $today = now()->toDateString();

        return [
            'vacancy_number' => $row->vacancy_number,
            'title' => $row->title,
            'department' => $this->tree()->path($row->department_id),
            'status' => ucwords(str_replace('_', ' ', $row->status)),
            'opened' => $row->opened_at ? substr((string) $row->opened_at, 0, 10) : 'Not recorded',
            'age_days' => self::days($row->opened_at, $today),
            'closing_date' => $row->closing_date ? substr((string) $row->closing_date, 0, 10) : null,
            'past_closing' => $row->closing_date && substr((string) $row->closing_date, 0, 10) < $today ? 'Yes' : 'No',
            'open_positions' => max(0, (int) $row->openings - (int) $row->filled_count),
            'active_applications' => (int) $row->active_applications,
        ];
    }

    public function summary(array $filters): array
    {
        $today = now()->toDateString();
        $vacancies = $this->vacancies($filters)->get(['v.opened_at', 'v.closing_date']);
        $counts = collect(self::BUCKETS)->map(fn () => 0)->all() + ['Not recorded' => 0];

        foreach ($vacancies as $v) {
            $age = self::days($v->opened_at, $today);
            $bucket = $age === null ? 'Not recorded' : collect(self::BUCKETS)->search(fn ($range) => $age >= $range[0] && $age <= $range[1]);
            $counts[$bucket]++;
        }

        $stats = self::dayStats($vacancies->map(fn ($v) => self::days($v->opened_at, $today)));

        return [
            $this->shareSection('Open Vacancies by Age', 'Age', collect($counts)->map(fn ($c, $label) => ['label' => $label, 'count' => $c])->values(), 'Vacancies'),
            [
                'title' => 'Age Statistics (days)',
                'columns' => ['Vacancies', 'Average', 'Median', 'Shortest', 'Longest', 'Past closing date'],
                'rows' => [[$stats['count'], $stats['average'], $stats['median'], $stats['min'], $stats['max'],
                    $vacancies->filter(fn ($v) => $v->closing_date && substr((string) $v->closing_date, 0, 10) < $today)->count()]],
            ],
        ];
    }
}
