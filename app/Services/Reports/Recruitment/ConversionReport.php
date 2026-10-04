<?php

namespace App\Services\Reports\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Accepted offers versus applicant-to-employee conversions.
 */
class ConversionReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'recruitment-conversion';
    }

    public function title(): string
    {
        return 'Hiring Conversion';
    }

    public function description(): string
    {
        return 'Accepted offers converted to employees, conversions in a period by type, department and vacancy.';
    }

    public function notes(): array
    {
        return [
            'Accepted offers: offers accepted in the period, and whether each has been converted to an employee (at any time up to today).',
            'Conversions: conversions recorded in the period, by conversion date.',
            'Days to convert = offer accepted → conversion, calendar days.',
        ];
    }

    public function summary(array $filters): array
    {
        $accepted = $this->inPeriod($this->applicationBase($filters)->join('job_offers as o', 'o.application_id', '=', 'a.id'), 'o.responded_at', $filters)
            ->where('o.response', 'accepted')
            ->leftJoin('application_conversions as c', 'c.job_offer_id', '=', 'o.id')
            ->get(['o.id', 'o.responded_at', 'c.converted_at']);
        $n = $accepted->count();
        $converted = $accepted->whereNotNull('converted_at')->count();
        $stats = self::dayStats($accepted->whereNotNull('converted_at')->map(fn ($r) => self::days($r->responded_at, $r->converted_at)));

        $conversions = fn () => $this->inPeriod($this->applicationBase($filters)->join('application_conversions as c', 'c.application_id', '=', 'a.id'), 'c.converted_at', $filters);

        $byType = $conversions()->groupBy('c.conversion_type')->select('c.conversion_type', DB::raw('count(*) as c'))->get()
            ->map(fn ($r) => ['label' => $r->conversion_type === 'existing_employee' ? 'Existing employee' : 'New employee', 'count' => (int) $r->c]);
        $accounts = $conversions()->groupBy('c.user_account')->select('c.user_account', DB::raw('count(*) as c'))->get()
            ->map(fn ($r) => ['label' => ['created' => 'Account created', 'existing' => 'Existing account', 'none' => 'No account'][$r->user_account] ?? self::label($r->user_account), 'count' => (int) $r->c]);
        $byDepartment = $conversions()->groupBy('v.department_id')->select('v.department_id', DB::raw('count(*) as c'))->get()
            ->map(fn ($r) => ['label' => self::label($this->tree()->path($r->department_id)), 'count' => (int) $r->c])->sortBy('label')->values();
        $byVacancy = $conversions()->groupBy('v.id', 'v.vacancy_number', 'v.title')->select('v.vacancy_number', 'v.title', DB::raw('count(*) as c'))
            ->orderBy('v.vacancy_number')->get()->map(fn ($r) => ['label' => "{$r->vacancy_number} · {$r->title}", 'count' => (int) $r->c]);

        return [
            [
                'title' => 'Offers Accepted in the Period',
                'columns' => ['Accepted', 'Converted', 'Awaiting conversion', 'Conversion rate', 'Average days to convert', 'Median days to convert'],
                'rows' => [[$n, $converted, $n - $converted, self::pct($converted, $n), $stats['average'], $stats['median']]],
            ],
            $this->shareSection('Conversions in the Period by Type', 'Type', $byType, 'Conversions'),
            $this->shareSection('Conversions in the Period by User Account', 'User account', $accounts, 'Conversions'),
            $this->shareSection('Conversions in the Period by Department', 'Department', $byDepartment, 'Conversions'),
            $this->shareSection('Conversions in the Period by Vacancy', 'Vacancy', $byVacancy, 'Conversions'),
        ];
    }
}
