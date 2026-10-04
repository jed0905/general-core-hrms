<?php

namespace App\Services\Reports\Recruitment;

/**
 * Time to hire = days from the candidate applying to accepting the offer.
 * Aggregate only (no per-candidate rows).
 */
class TimeToHireReport extends RecruitmentReport
{
    public static function key(): string
    {
        return 'time-to-hire';
    }

    public function title(): string
    {
        return 'Time to Hire';
    }

    public function description(): string
    {
        return 'Days from application to offer acceptance (and to employee conversion), by department and source.';
    }

    protected function filterKeys(): array
    {
        return [...parent::filterKeys(), 'recruitment_source_id'];
    }

    public function notes(): array
    {
        return [
            'Primary measure: application date → offer accepted, for offers accepted in the period.',
            'Secondary measure: application date → converted to employee, for conversions in the period.',
            'Calendar days. Applications entered by HR use the application date HR recorded.',
        ];
    }

    public function summary(array $filters): array
    {
        $accepted = $this->inPeriod($this->applicationBase($filters)->join('job_offers as o', 'o.application_id', '=', 'a.id'), 'o.responded_at', $filters)
            ->where('o.response', 'accepted')
            ->leftJoin('recruitment_sources as rs', 'rs.id', '=', 'a.recruitment_source_id')
            ->get(['a.applied_at', 'o.responded_at as ended_at', 'v.department_id', 'rs.name as source']);

        $converted = $this->inPeriod($this->applicationBase($filters)->join('application_conversions as c', 'c.application_id', '=', 'a.id'), 'c.converted_at', $filters)
            ->get(['a.applied_at', 'c.converted_at as ended_at']);

        $days = fn ($rows) => $rows->map(fn ($r) => self::days($r->applied_at, $r->ended_at));
        $line = fn (string $label, $rows) => [$label, ...array_values(self::dayStats($days($rows)))];
        $grouped = fn ($key) => $accepted->groupBy($key)->sortKeys()->map(fn ($g, $label) => $line(self::label($label), $g))->values()->all();

        $columns = ['Hires measured', 'Average', 'Median', 'Shortest', 'Longest'];

        return [
            [
                'title' => 'Time to Hire (days)',
                'columns' => ['Measure', ...$columns],
                'rows' => [
                    $line('Applied → offer accepted', $accepted),
                    $line('Applied → converted to employee', $converted),
                ],
            ],
            [
                'title' => 'By Department (applied → offer accepted)',
                'columns' => ['Department', ...$columns],
                'rows' => $grouped(fn ($r) => $this->tree()->path($r->department_id) ?? ''),
            ],
            [
                'title' => 'By Source (applied → offer accepted)',
                'columns' => ['Source', ...$columns],
                'rows' => $grouped(fn ($r) => $r->source ?? ''),
            ],
        ];
    }
}
