<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Assessment and scorecard activity. Counts only: no scores, ratings,
 * recommendations or comments are shown.
 */
class AssessmentEvaluationReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-assessments';
    }

    public function title(): string
    {
        return 'Assessments & Evaluations';
    }

    public function description(): string
    {
        return 'Assessments created and completed in a period, pass results, and interview scorecard completion.';
    }

    public function notes(): array
    {
        return [
            'Assessments created are counted by creation date; completed ones by completion date. Pass/fail is shown only for assessment types that record it.',
            'Scorecard completion: for interviews completed in the period, each panelist is expected to submit one scorecard. Submitted = scorecards submitted for those interviews (at any time).',
            'Scores, ratings, recommendations and comments are deliberately not reported.',
        ];
    }

    public function summary(array $filters): array
    {
        $assessments = fn () => $this->applicationBase($filters)->join('application_assessments as x', 'x.application_id', '=', 'a.id');

        $created = $this->inPeriod($assessments(), 'x.created_at', $filters)
            ->groupBy('x.status')->select('x.status', DB::raw('count(*) as c'))->pluck('c', 'status');

        $completed = fn () => $this->inPeriod($assessments(), 'x.completed_at', $filters)->where('x.status', 'completed');
        $byType = $completed()->leftJoin('assessment_types as t', 't.id', '=', 'x.assessment_type_id')->groupBy('t.name')
            ->select('t.name', DB::raw('count(*) as total'),
                DB::raw('sum(case when x.passed = 1 then 1 else 0 end) as passed'),
                DB::raw('sum(case when x.passed = 0 then 1 else 0 end) as failed'))
            ->orderBy('t.name')->get();

        $interviews = $this->inPeriod($this->applicationBase($filters)->join('application_interviews as i', 'i.application_id', '=', 'a.id'), 'i.completed_at', $filters)
            ->where('i.status', 'completed');
        $expected = (clone $interviews)->join('interview_panelists as p', 'p.application_interview_id', '=', 'i.id')->count();
        $submitted = (clone $interviews)->join('interview_panelists as p', 'p.application_interview_id', '=', 'i.id')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('application_evaluations as ev')
                ->whereColumn('ev.application_interview_id', 'i.id')->whereColumn('ev.evaluator_employee_id', 'p.employee_id')->where('ev.status', 'submitted'))
            ->count();

        $submittedInPeriod = $this->inPeriod($this->applicationBase($filters)->join('application_evaluations as ev', 'ev.application_id', '=', 'a.id'), 'ev.submitted_at', $filters)
            ->where('ev.status', 'submitted');

        return [
            [
                'title' => 'Assessments Created in the Period, by Current Status',
                'columns' => ['Status', 'Assessments'],
                'rows' => collect(['scheduled', 'in_progress', 'completed', 'cancelled'])->map(fn ($s) => [ucwords(str_replace('_', ' ', $s)), (int) ($created[$s] ?? 0)])->all(),
            ],
            [
                'title' => 'Assessments Completed in the Period, by Type',
                'columns' => ['Type', 'Completed', 'Passed', 'Failed', 'Pass rate'],
                'rows' => $byType->map(fn ($t) => [self::label($t->name), (int) $t->total, (int) $t->passed, (int) $t->failed, self::pct((int) $t->passed, (int) $t->passed + (int) $t->failed)])->all(),
            ],
            [
                'title' => 'Interview Scorecards',
                'columns' => ['Scorecards expected', 'Submitted', 'Outstanding', 'Completion rate', 'Submitted in the period', 'Applications evaluated'],
                'rows' => [[$expected, $submitted, $expected - $submitted, self::pct($submitted, $expected),
                    (clone $submittedInPeriod)->count(), $this->countDistinct($submittedInPeriod, 'a.id')]],
            ],
        ];
    }
}
