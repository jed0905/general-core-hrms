<?php

namespace App\Services\Reports\Recruitment;

use App\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * Careers portal activity from the portal's own event log
 * (recruitment_portal_events). Page views are not recorded.
 */
class CareersPortalReport extends RecruitmentReport
{
    public const EVENTS = [
        'account_registered' => 'Account registrations started',
        'account_activated' => 'Accounts activated',
        'applicant_created' => 'New applicant profiles',
        'applicant_linked' => 'Linked to an existing applicant',
        'profile_updated' => 'Profile updates',
        'document_uploaded' => 'Documents uploaded',
        'document_deleted' => 'Documents deleted',
        'application_submitted' => 'Applications submitted online',
        'application_withdrawn' => 'Applications withdrawn by candidates',
        'vacancy_published' => 'Vacancies published',
        'vacancy_unpublished' => 'Vacancies unpublished',
    ];

    public static function key(): string
    {
        return 'careers-portal';
    }

    public function title(): string
    {
        return 'Careers Portal';
    }

    public function description(): string
    {
        return 'Candidate account, application and publication activity on the careers portal.';
    }

    public function notes(): array
    {
        return [
            'From the careers portal event log, by event date. Page views and visits are not recorded, so traffic and view-to-apply conversion can\'t be reported.',
            'Account and profile events aren\'t tied to a vacancy: the department, vacancy and hiring manager filters apply only to vacancy-related events.',
            'Online share = applications received in the period with no HR user recorded (submitted by the candidate) ÷ all applications received.',
        ];
    }

    public function summary(array $filters): array
    {
        $scoped = ! empty($filters['department_id']) || ! empty($filters['vacancy_id']) || ! empty($filters['hiring_manager_id']);
        $vacancyIds = $this->scopeVacancies(DB::table('vacancies as v'), $filters)->select('v.id');

        $events = $this->inPeriod(DB::table('recruitment_portal_events as pe'), 'pe.occurred_at', $filters)
            ->when($scoped, fn ($q) => $q->where(fn ($q) => $q->whereNull('pe.vacancy_id')->orWhereIn('pe.vacancy_id', $vacancyIds)))
            ->groupBy('pe.event')->select('pe.event', DB::raw('count(*) as c'), DB::raw('count(distinct pe.applicant_account_id) as accounts'))
            ->get()->keyBy('event');

        $applications = $this->inPeriod($this->applicationBase($filters), 'a.applied_at', $filters);
        $total = $this->countDistinct($applications, 'a.id');
        $online = $this->countDistinct((clone $applications)->whereNull('a.created_by'), 'a.id');

        $byVacancy = $this->inPeriod($this->applicationBase($filters), 'a.applied_at', $filters)->groupBy('v.id', 'v.vacancy_number', 'v.title')
            ->select('v.vacancy_number', 'v.title', DB::raw('count(*) as total'), DB::raw('sum(case when a.created_by is null then 1 else 0 end) as online'))
            ->havingRaw('sum(case when a.created_by is null then 1 else 0 end) > 0')
            ->orderBy('v.vacancy_number')->get();

        $published = Vacancy::publiclyVisible()->whereIn('id', $vacancyIds)->count();

        return [
            [
                'title' => 'Portal Activity in the Period',
                'columns' => ['Event', 'Events', 'Candidate accounts'],
                'rows' => collect(self::EVENTS)->map(fn ($label, $event) => [$label, (int) ($events[$event]->c ?? 0), (int) ($events[$event]->accounts ?? 0)])->values()->all(),
            ],
            [
                'title' => 'Online Share of Applications (received in the period)',
                'columns' => ['All applications', 'Submitted online', 'Entered by HR', 'Online share'],
                'rows' => [[$total, $online, $total - $online, self::pct($online, $total)]],
            ],
            [
                'title' => 'Online Applications by Vacancy',
                'columns' => ['Vacancy', 'Applications', 'Online', 'Online share'],
                'rows' => $byVacancy->map(fn ($v) => ["{$v->vacancy_number} · {$v->title}", (int) $v->total, (int) $v->online, self::pct((int) $v->online, (int) $v->total)])->all(),
            ],
            [
                'title' => 'Publicly Listed Vacancies (today)',
                'columns' => ['Vacancies'],
                'rows' => [[$published]],
            ],
        ];
    }
}
