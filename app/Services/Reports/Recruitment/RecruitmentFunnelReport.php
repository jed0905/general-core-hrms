<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Three views that are deliberately kept apart:
 * - cohort: applications received in the period and how far each ever got;
 * - activity: what happened in the period, each event by its own timestamp;
 * - current: where open applications are now (ignores the period).
 * Every figure counts distinct applications.
 */
class RecruitmentFunnelReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-funnel';
    }

    public function title(): string
    {
        return 'Recruitment Funnel';
    }

    public function description(): string
    {
        return 'Applications received in a period and how far they progressed, period activity, and the current pipeline.';
    }

    protected function filterKeys(): array
    {
        return [...parent::filterKeys(), 'recruitment_source_id'];
    }

    public function notes(): array
    {
        return [
            'Cohort: applications received (applied date) in the period; each step counts distinct applications that ever reached it, whatever happened later.',
            'Stages can be skipped, so a step can exceed the one before it. "% of previous" is shown as recorded, never capped.',
            'Period activity counts each event by its own date (stage history, selection, offer issue/response, conversion), so the rows are not a funnel.',
            'Current pipeline ignores the date range and shows applications as they are today.',
        ];
    }

    public function summary(array $filters): array
    {
        return [$this->cohortSection($filters), $this->activitySection($filters), $this->currentSection($filters)];
    }

    protected function cohortSection(array $filters): array
    {
        $counts = $this->funnelCounts($this->inPeriod($this->applicationBase($filters), 'a.applied_at', $filters));
        $applied = $counts['Applied'];
        $previous = null;
        $rows = [];

        foreach ($counts as $step => $count) {
            $rows[] = [$step, $count, self::pct($count, $applied), $previous === null ? '—' : self::pct($count, $previous)];
            $previous = $count;
        }

        return [
            'title' => 'Cohort Funnel (applications received in the period)',
            'columns' => ['Step', 'Applications', '% of applied', '% of previous'],
            'rows' => $rows,
        ];
    }

    protected function activitySection(array $filters): array
    {
        $base = fn () => $this->applicationBase($filters);
        $rows = [['Applications received', $this->countDistinct($this->inPeriod($base(), 'a.applied_at', $filters), 'a.id')]];

        foreach (array_slice(self::STAGE_TYPES, 1, null, true) as $type => $label) {
            $q = $this->inPeriod($base()->join('application_stage_histories as h', 'h.application_id', '=', 'a.id')
                ->join('vacancy_stages as vs', 'vs.id', '=', 'h.to_vacancy_stage_id')
                ->where('vs.stage_type', $type), 'h.acted_at', $filters);
            $rows[] = ["Moved into {$label}", $this->countDistinct($q, 'a.id')];
        }

        foreach (['rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'] as $action => $label) {
            $q = $this->inPeriod($base()->join('application_stage_histories as h', 'h.application_id', '=', 'a.id')->where('h.action', $action), 'h.acted_at', $filters);
            $rows[] = [$label, $this->countDistinct($q, 'a.id')];
        }

        $rows[] = ['Selected', $this->countDistinct($this->inPeriod($base()->join('application_selections as s', 's.application_id', '=', 'a.id')->where('s.decision', 'selected'), 's.decided_at', $filters), 'a.id')];
        $rows[] = ['Offer issued', $this->countDistinct($this->inPeriod($base()->join('job_offers as o', 'o.application_id', '=', 'a.id'), 'o.issued_at', $filters), 'a.id')];
        $rows[] = ['Offer accepted', $this->countDistinct($this->inPeriod($base()->join('job_offers as o', 'o.application_id', '=', 'a.id')->where('o.response', 'accepted'), 'o.responded_at', $filters), 'a.id')];
        $rows[] = ['Hired (converted)', $this->countDistinct($this->inPeriod($base()->join('application_conversions as c', 'c.application_id', '=', 'a.id'), 'c.converted_at', $filters), 'a.id')];

        return [
            'title' => 'Activity in the Period (each event by its own date)',
            'columns' => ['Event', 'Applications'],
            'rows' => $rows,
        ];
    }

    protected function currentSection(array $filters): array
    {
        $open = $this->applicationBase($filters)
            ->join('vacancy_stages as cs', 'cs.id', '=', 'a.current_vacancy_stage_id')
            ->whereIn('a.status', ['active', 'shortlisted'])
            ->groupBy('cs.stage_type')
            ->select('cs.stage_type', DB::raw('count(distinct a.id) as c'))
            ->pluck('c', 'stage_type');

        $rows = collect(self::STAGE_TYPES)->map(fn ($label, $type) => ["In {$label}", (int) ($open[$type] ?? 0)])->values()->all();

        $closed = $this->applicationBase($filters)->whereIn('a.status', ['rejected', 'withdrawn'])
            ->groupBy('a.status')->select('a.status', DB::raw('count(distinct a.id) as c'))->pluck('c', 'status');
        $rows[] = ['Rejected (closed)', (int) ($closed['rejected'] ?? 0)];
        $rows[] = ['Withdrawn (closed)', (int) ($closed['withdrawn'] ?? 0)];

        return [
            'title' => 'Current Pipeline (today, all dates)',
            'columns' => ['Where now', 'Applications'],
            'rows' => $rows,
        ];
    }
}
