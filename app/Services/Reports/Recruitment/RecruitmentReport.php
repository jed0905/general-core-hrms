<?php

namespace App\Services\Reports\Recruitment;

use App\Services\Reports\Concerns\FiltersRecruitment;
use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Base for the recruitment reports (category "recruitment", permission
 * report.recruitment). Read-only: every query is a plain SELECT.
 */
abstract class RecruitmentReport extends Report
{
    use FiltersRecruitment;

    /** Stage types in pipeline order (vacancy_stages.stage_type). */
    public const STAGE_TYPES = [
        'applied' => 'Applied',
        'screening' => 'Screening',
        'shortlisted' => 'Shortlisted',
        'interview' => 'Interview',
        'assessment' => 'Assessment',
        'evaluation' => 'Evaluation',
    ];

    public static function category(): string
    {
        return 'recruitment';
    }

    /**
     * @return array<int, string>
     */
    protected function filterKeys(): array
    {
        return ['date_from', 'date_to', 'department_id', 'include_sub_departments', 'vacancy_id', 'hiring_manager_id'];
    }

    public function filters(): array
    {
        return $this->recruitmentFilters($this->filterKeys());
    }

    public function rules(): array
    {
        return array_intersect_key($this->recruitmentRules(), array_flip($this->filterKeys()));
    }

    /**
     * "The application has ever entered a stage of this type" (stage history).
     */
    protected function reachedStage(string $type): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))
            ->from('application_stage_histories as hs')
            ->join('vacancy_stages as vs', 'vs.id', '=', 'hs.to_vacancy_stage_id')
            ->whereColumn('hs.application_id', 'a.id')
            ->where('vs.stage_type', $type);
    }

    protected function hasSelection(): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))->from('application_selections as s')
            ->whereColumn('s.application_id', 'a.id')->where('s.decision', 'selected');
    }

    protected function hasIssuedOffer(): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))->from('job_offers as o')
            ->whereColumn('o.application_id', 'a.id')->whereNotNull('o.issued_at');
    }

    protected function hasAcceptedOffer(): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))->from('job_offers as o')
            ->whereColumn('o.application_id', 'a.id')->where('o.response', 'accepted')->whereNotNull('o.responded_at');
    }

    protected function hasConversion(): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))->from('application_conversions as c')->whereColumn('c.application_id', 'a.id');
    }

    /**
     * Distinct applications for each funnel step of an application query.
     *
     * @return array<string, int>
     */
    protected function funnelCounts(Builder $applications): array
    {
        $counts = ['Applied' => $this->countDistinct($applications, 'a.id')];

        foreach (array_slice(self::STAGE_TYPES, 1, null, true) as $type => $label) {
            $counts["Reached {$label}"] = $this->countDistinct((clone $applications)->whereExists($this->reachedStage($type)), 'a.id');
        }

        $counts['Selected'] = $this->countDistinct((clone $applications)->whereExists($this->hasSelection()), 'a.id');
        $counts['Offer issued'] = $this->countDistinct((clone $applications)->whereExists($this->hasIssuedOffer()), 'a.id');
        $counts['Offer accepted'] = $this->countDistinct((clone $applications)->whereExists($this->hasAcceptedOffer()), 'a.id');
        $counts['Hired (converted)'] = $this->countDistinct((clone $applications)->whereExists($this->hasConversion()), 'a.id');

        return $counts;
    }

    /**
     * Label => count rows with a share of the total ("—" when the total is 0).
     */
    protected function shareSection(string $title, string $heading, iterable $counts, string $countHeading = 'Count'): array
    {
        $counts = collect($counts);
        $total = $counts->sum('count');

        return [
            'title' => $title,
            'columns' => [$heading, $countHeading, '%'],
            'rows' => $counts->map(fn ($c) => [$c['label'], (int) $c['count'], self::pct($c['count'], $total)])->values()->all(),
        ];
    }

    protected function employeeName(?int $employeeId): ?string
    {
        if (! $employeeId) {
            return null;
        }

        $e = DB::table('employees')->where('id', $employeeId)->first(['emp_last_name', 'emp_first_name']);

        return $e ? self::personName($e->emp_last_name, $e->emp_first_name) : null;
    }

    /**
     * Display names for HR users (their employee name, else the username).
     *
     * @param  iterable<int>  $userIds
     * @return array<int, string>
     */
    protected function userNames(iterable $userIds): array
    {
        return DB::table('users as u')
            ->leftJoin('employees as e', 'e.id', '=', 'u.employee_id')
            ->whereIn('u.id', collect($userIds)->filter()->unique()->values())
            ->get(['u.id', 'u.username', 'e.emp_last_name', 'e.emp_first_name'])
            ->mapWithKeys(fn ($u) => [$u->id => self::personName($u->emp_last_name, $u->emp_first_name) ?? $u->username])
            ->all();
    }
}
