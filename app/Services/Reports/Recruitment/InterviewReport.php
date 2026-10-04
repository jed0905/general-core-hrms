<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Interviews whose (current) start time falls in the period. Panelists are
 * internal employees, so their workload is shown by name; candidates are not.
 */
class InterviewReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-interviews';
    }

    public function title(): string
    {
        return 'Interviews';
    }

    public function description(): string
    {
        return 'Interviews in a period by status, type, vacancy and month, rescheduling and panelist workload.';
    }

    public function notes(): array
    {
        return [
            'Interviews are counted by their scheduled start date. A rescheduled interview counts once, at its latest start date.',
            'Completion rate = completed ÷ (completed + cancelled); interviews still scheduled are excluded. Attendance and no-shows are not recorded.',
            '"Applications interviewed" counts distinct applications with at least one completed interview.',
        ];
    }

    public function summary(array $filters): array
    {
        $interviews = fn () => $this->inPeriod($this->applicationBase($filters)->join('application_interviews as i', 'i.application_id', '=', 'a.id'), 'i.starts_at', $filters);

        $status = $interviews()->groupBy('i.status')->select('i.status', DB::raw('count(*) as c'))->pluck('c', 'status');
        $completed = (int) ($status['completed'] ?? 0);
        $cancelled = (int) ($status['cancelled'] ?? 0);
        $scheduled = (int) ($status['scheduled'] ?? 0);
        $rescheduled = $interviews()->whereExists(fn ($q) => $q->select(DB::raw(1))->from('interview_reschedules as r')->whereColumn('r.application_interview_id', 'i.id'))->count();

        $byType = $interviews()->leftJoin('interview_types as t', 't.id', '=', 'i.interview_type_id')->groupBy('t.name')
            ->select('t.name', DB::raw('count(*) as total'), DB::raw("sum(case when i.status = 'completed' then 1 else 0 end) as completed"))
            ->orderBy('t.name')->get();

        $byVacancy = $interviews()->groupBy('v.id', 'v.vacancy_number', 'v.title')
            ->select('v.vacancy_number', 'v.title', DB::raw('count(*) as total'),
                DB::raw("sum(case when i.status = 'completed' then 1 else 0 end) as completed"),
                DB::raw("count(distinct case when i.status = 'completed' then a.id end) as applications"))
            ->orderBy('v.vacancy_number')->get();

        $byMonth = $interviews()->get(['i.starts_at', 'i.status'])
            ->groupBy(fn ($i) => substr((string) $i->starts_at, 0, 7))->sortKeys()
            ->map(fn ($g, $month) => [$month, $g->count(), $g->where('status', 'completed')->count(), $g->where('status', 'cancelled')->count()])
            ->values()->all();

        $panelists = $interviews()->join('interview_panelists as p', 'p.application_interview_id', '=', 'i.id')
            ->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->groupBy('p.employee_id', 'e.emp_last_name', 'e.emp_first_name')
            ->select('e.emp_last_name', 'e.emp_first_name', DB::raw('count(*) as total'),
                DB::raw("sum(case when i.status = 'completed' then 1 else 0 end) as completed"),
                DB::raw("sum(case when i.status = 'scheduled' then 1 else 0 end) as upcoming"))
            ->orderByDesc('total')->orderBy('e.emp_last_name')->get();

        return [
            [
                'title' => 'Interviews in the Period',
                'columns' => ['Interviews', 'Completed', 'Cancelled', 'Still scheduled', 'Rescheduled', 'Completion rate', 'Applications interviewed'],
                'rows' => [[$completed + $cancelled + $scheduled, $completed, $cancelled, $scheduled, $rescheduled, self::pct($completed, $completed + $cancelled),
                    $this->countDistinct($interviews()->where('i.status', 'completed'), 'a.id')]],
            ],
            [
                'title' => 'By Interview Type',
                'columns' => ['Type', 'Interviews', 'Completed'],
                'rows' => $byType->map(fn ($t) => [self::label($t->name), (int) $t->total, (int) $t->completed])->all(),
            ],
            [
                'title' => 'By Vacancy',
                'columns' => ['Vacancy', 'Interviews', 'Completed', 'Applications interviewed'],
                'rows' => $byVacancy->map(fn ($v) => ["{$v->vacancy_number} · {$v->title}", (int) $v->total, (int) $v->completed, (int) $v->applications])->all(),
            ],
            [
                'title' => 'By Month',
                'columns' => ['Month', 'Interviews', 'Completed', 'Cancelled'],
                'rows' => $byMonth,
            ],
            [
                'title' => 'Panelist Workload',
                'columns' => ['Panelist', 'Interviews', 'Completed', 'Still scheduled'],
                'rows' => $panelists->map(fn ($p) => [self::personName($p->emp_last_name, $p->emp_first_name), (int) $p->total, (int) $p->completed, (int) $p->upcoming])->all(),
            ],
        ];
    }
}
