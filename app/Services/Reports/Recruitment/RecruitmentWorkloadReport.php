<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Workload that the data can actually attribute: hiring managers through
 * vacancies.hiring_manager_id, and HR users through the actor recorded on
 * each action. There is no recruiter-ownership field, so no "recruiter"
 * metric is invented.
 */
class RecruitmentWorkloadReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-workload';
    }

    public function title(): string
    {
        return 'Hiring Manager & HR Workload';
    }

    public function description(): string
    {
        return 'Vacancies and pipeline per hiring manager, and recorded recruitment actions per HR user.';
    }

    public function notes(): array
    {
        return [
            'Hiring managers come from the vacancy record. Pipeline counts are for applications received in the period.',
            'HR actions are attributed to the user recorded on each action (applications entered, stage moves, screenings, offers, conversions), counted by the action\'s own date.',
            'Vacancies have no assigned recruiter, so recruiter ownership is not reported. Candidate self-service actions (careers portal) have no HR user and are excluded.',
        ];
    }

    public function summary(array $filters): array
    {
        return [$this->hiringManagers($filters), $this->hrActions($filters)];
    }

    protected function hiringManagers(array $filters): array
    {
        $rows = $this->scopeVacancies(DB::table('vacancies as v'), $filters)
            ->leftJoin('employees as e', 'e.id', '=', 'v.hiring_manager_id')
            ->groupBy('v.hiring_manager_id', 'e.emp_last_name', 'e.emp_first_name')
            ->select('v.hiring_manager_id', 'e.emp_last_name', 'e.emp_first_name',
                DB::raw("sum(case when v.status in ('open', 'on_hold') then 1 else 0 end) as open_vacancies"),
                DB::raw("sum(case when v.status in ('open', 'on_hold') then v.openings else 0 end) as open_openings"))
            ->get()->keyBy(fn ($r) => (string) $r->hiring_manager_id);

        $count = fn ($query) => $query->groupBy('v.hiring_manager_id')->select('v.hiring_manager_id', DB::raw('count(distinct a.id) as c'))
            ->pluck('c', 'hiring_manager_id')->mapWithKeys(fn ($c, $id) => [(string) $id => (int) $c]);

        $applied = fn () => $this->inPeriod($this->applicationBase($filters), 'a.applied_at', $filters);
        $received = $count($applied());
        $interviewed = $count($applied()->whereExists($this->reachedStage('interview')));
        $hired = $count($applied()->whereExists($this->hasConversion()));

        $evaluations = $this->inPeriod($this->applicationBase($filters)->join('application_evaluations as ev', 'ev.application_id', '=', 'a.id'), 'ev.submitted_at', $filters)
            ->where('ev.status', 'submitted')->whereColumn('ev.evaluator_employee_id', 'v.hiring_manager_id')
            ->groupBy('v.hiring_manager_id')->select('v.hiring_manager_id', DB::raw('count(*) as c'))->pluck('c', 'hiring_manager_id')
            ->mapWithKeys(fn ($c, $id) => [(string) $id => (int) $c]);

        return [
            'title' => 'Hiring Managers',
            'columns' => ['Hiring manager', 'Open vacancies', 'Open positions', 'Applications (period)', 'Reached interview', 'Hired', 'Own scorecards submitted (period)'],
            'rows' => $rows->sortBy(fn ($r) => $r->emp_last_name ?? "\u{FFFF}")->map(fn ($r, $id) => [
                $id === '' ? 'Not assigned' : self::label(self::personName($r->emp_last_name, $r->emp_first_name)),
                (int) $r->open_vacancies,
                (int) $r->open_openings,
                $received[$id] ?? 0,
                $interviewed[$id] ?? 0,
                $hired[$id] ?? 0,
                $evaluations[$id] ?? 0,
            ])->values()->all(),
        ];
    }

    protected function hrActions(array $filters): array
    {
        $byActor = fn ($query, string $actor) => $query->whereNotNull($actor)->groupBy($actor)
            ->select(DB::raw("{$actor} as actor"), DB::raw('count(*) as c'))->pluck('c', 'actor');

        $metrics = [
            'Applications entered' => $byActor($this->inPeriod($this->applicationBase($filters), 'a.applied_at', $filters), 'a.created_by'),
            'Stage moves' => $byActor($this->inPeriod($this->applicationBase($filters)->join('application_stage_histories as h', 'h.application_id', '=', 'a.id'), 'h.acted_at', $filters)
                ->whereIn('h.action', ['moved', 'shortlisted']), 'h.acted_by'),
            'Rejections' => $byActor($this->inPeriod($this->applicationBase($filters)->join('application_stage_histories as h', 'h.application_id', '=', 'a.id'), 'h.acted_at', $filters)
                ->where('h.action', 'rejected'), 'h.acted_by'),
            'Screenings' => $byActor($this->inPeriod($this->applicationBase($filters)->join('application_screenings as sc', 'sc.application_id', '=', 'a.id'), 'sc.screened_on', $filters), 'sc.screened_by'),
            'Interviews scheduled' => $byActor($this->inPeriod($this->applicationBase($filters)->join('application_interviews as i', 'i.application_id', '=', 'a.id'), 'i.created_at', $filters), 'i.created_by'),
            'Offers drafted' => $byActor($this->inPeriod($this->applicationBase($filters)->join('job_offers as o', 'o.application_id', '=', 'a.id'), 'o.created_at', $filters), 'o.created_by'),
            'Offers issued' => $byActor($this->inPeriod($this->applicationBase($filters)->join('job_offers as o', 'o.application_id', '=', 'a.id'), 'o.issued_at', $filters), 'o.issued_by'),
            'Conversions' => $byActor($this->inPeriod($this->applicationBase($filters)->join('application_conversions as c', 'c.application_id', '=', 'a.id'), 'c.converted_at', $filters), 'c.converted_by'),
        ];

        $users = collect($metrics)->flatMap(fn ($m) => $m->keys())->unique();
        $names = $this->userNames($users);

        return [
            'title' => 'HR Actions by User (in the period)',
            'columns' => ['User', ...array_keys($metrics), 'Total'],
            'rows' => $users->map(function ($id) use ($metrics, $names) {
                $values = collect($metrics)->map(fn ($m) => (int) ($m[$id] ?? 0))->values()->all();

                return [$names[$id] ?? "User #{$id}", ...$values, array_sum($values)];
            })->sortBy(fn ($row) => -end($row))->values()->all(),
        ];
    }
}
