<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Job offer lifecycle. Salary and offer terms are never reported.
 */
class OfferReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-offers';
    }

    public function title(): string
    {
        return 'Offers';
    }

    public function description(): string
    {
        return 'Offer lifecycle events in a period, approval and acceptance rates, and response times.';
    }

    public function notes(): array
    {
        return [
            'Lifecycle events are counted by their own timestamps, so one offer can appear in several rows.',
            'Outcome rates use the offers issued in the period (cohort) and their status today; "Awaiting response" offers are still issued.',
            'Approval rate = approved ÷ (approved + rejected) among approval decisions in the period.',
            'Salary, benefits and other offer terms are not reported.',
        ];
    }

    public function summary(array $filters): array
    {
        $offers = fn () => $this->scopeVacancies(DB::table('job_offers as o')->join('vacancies as v', 'v.id', '=', 'o.vacancy_id'), $filters);
        $event = fn (string $column, ?string $response = null) => $this->inPeriod($offers(), "o.{$column}", $filters)
            ->when($response, fn ($q) => $q->where('o.response', $response))->count();

        $approved = $event('approved_at');
        $rejected = $event('rejected_at');

        $issued = $this->inPeriod($offers(), 'o.issued_at', $filters)->get(['o.status', 'o.issued_at', 'o.responded_at', 'o.response', 'v.vacancy_number', 'v.title']);
        $n = $issued->count();
        $outcomes = ['accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired', 'withdrawn' => 'Withdrawn', 'issued' => 'Awaiting response'];
        $responseDays = $issued->whereNotNull('responded_at')->map(fn ($o) => self::days($o->issued_at, $o->responded_at));
        $stats = self::dayStats($responseDays);

        return [
            [
                'title' => 'Lifecycle Events in the Period',
                'columns' => ['Event', 'Offers'],
                'rows' => [
                    ['Drafted', $event('created_at')],
                    ['Submitted for approval', $event('submitted_at')],
                    ['Approved', $approved],
                    ['Rejected in approval', $rejected],
                    ['Issued to candidate', $event('issued_at')],
                    ['Accepted', $event('responded_at', 'accepted')],
                    ['Declined', $event('responded_at', 'declined')],
                    ['Expired', $event('expired_at')],
                    ['Withdrawn', $event('withdrawn_at')],
                ],
            ],
            [
                'title' => 'Approval',
                'columns' => ['Approved', 'Rejected', 'Approval rate'],
                'rows' => [[$approved, $rejected, self::pct($approved, $approved + $rejected)]],
            ],
            [
                'title' => 'Outcome of Offers Issued in the Period (status today)',
                'columns' => ['Outcome', 'Offers', '%'],
                'rows' => collect($outcomes)->map(fn ($label, $status) => [$label, $issued->where('status', $status)->count(), self::pct($issued->where('status', $status)->count(), $n)])->values()->all(),
            ],
            [
                'title' => 'Days from Issue to Response (offers issued in the period)',
                'columns' => ['Responses', 'Average', 'Median', 'Shortest', 'Longest'],
                'rows' => [[$stats['count'], $stats['average'], $stats['median'], $stats['min'], $stats['max']]],
            ],
            [
                'title' => 'Offers Issued in the Period by Vacancy',
                'columns' => ['Vacancy', 'Issued', 'Accepted', 'Declined', 'Acceptance rate'],
                'rows' => $issued->groupBy(fn ($o) => "{$o->vacancy_number} · {$o->title}")->sortKeys()
                    ->map(fn ($g, $vacancy) => [$vacancy, $g->count(), $g->where('response', 'accepted')->count(), $g->where('response', 'declined')->count(), self::pct($g->where('response', 'accepted')->count(), $g->count())])
                    ->values()->all(),
            ],
        ];
    }
}
