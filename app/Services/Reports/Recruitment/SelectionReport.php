<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Selection decisions made in the period (append-only decision log).
 */
class SelectionReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-selection';
    }

    public function title(): string
    {
        return 'Selection';
    }

    public function description(): string
    {
        return 'Selection decisions in a period, reversals, and selections against openings by vacancy.';
    }

    public function notes(): array
    {
        return [
            'Decisions are counted by decision date. Each decision is a separate record, so a reversed decision appears as two decisions; "Applications" counts each application once per decision type.',
            'Automatic decisions (made by the system) are shown separately from decisions made by HR.',
            '"Selected now" is the latest decision per application today, whatever the period.',
        ];
    }

    public function summary(array $filters): array
    {
        $decisions = fn () => $this->inPeriod($this->applicationBase($filters)->join('application_selections as s', 's.application_id', '=', 'a.id'), 's.decided_at', $filters);

        $rows = [];
        foreach ([false => 'By HR', true => 'Automatic'] as $automatic => $who) {
            foreach (['selected' => 'Selected', 'not_selected' => 'Not selected'] as $decision => $label) {
                $q = $decisions()->where('s.is_automatic', $automatic)->where('s.decision', $decision);
                $rows[] = ["{$label} ({$who})", (clone $q)->count(), $this->countDistinct($q, 'a.id')];
            }
        }

        $reversed = $this->countDistinct(
            $decisions()->where('s.decision', 'not_selected')->whereExists(fn ($q) => $q->select(DB::raw(1))->from('application_selections as p')
                ->whereColumn('p.application_id', 's.application_id')->where('p.decision', 'selected')->whereColumn('p.id', '<', 's.id')),
            'a.id'
        );

        $latest = DB::table('application_selections')->selectRaw('max(id) as id')->groupBy('application_id');
        $byVacancy = $this->scopeVacancies(DB::table('vacancies as v'), $filters)
            ->whereIn('v.id', $decisions()->select('v.id'))
            ->select('v.vacancy_number', 'v.title', 'v.openings')
            ->selectSub(fn ($q) => $q->from('application_selections as ls')->whereIn('ls.id', $latest)
                ->whereColumn('ls.vacancy_id', 'v.id')->where('ls.decision', 'selected')->selectRaw('count(*)'), 'selected_now')
            ->orderBy('v.vacancy_number')->get();

        return [
            [
                'title' => 'Decisions in the Period',
                'columns' => ['Decision', 'Decisions', 'Applications'],
                'rows' => [...$rows, ['Selections reversed in the period', $reversed, $reversed]],
            ],
            [
                'title' => 'Vacancies with Decisions in the Period',
                'columns' => ['Vacancy', 'Openings', 'Selected now', 'Openings left'],
                'rows' => $byVacancy->map(fn ($v) => ["{$v->vacancy_number} · {$v->title}", (int) $v->openings, (int) $v->selected_now, max(0, (int) $v->openings - (int) $v->selected_now)])->all(),
            ],
        ];
    }
}
