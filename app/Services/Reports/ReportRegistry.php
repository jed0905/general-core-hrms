<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Attendance\AttendanceLogReport;
use App\Services\Reports\Attendance\AttendanceSummaryReport;
use App\Services\Reports\Employee\DepartmentReport;
use App\Services\Reports\Employee\EmployeeDemographicsReport;
use App\Services\Reports\Employee\EmployeeMasterlistReport;
use App\Services\Reports\Employee\EmployeeMovementReport;
use App\Services\Reports\Employee\EmploymentStatusReport;
use App\Services\Reports\Employee\HeadcountReport;
use App\Services\Reports\Employee\JobTitleReport;
use App\Services\Reports\Employee\NewHiresReport;
use App\Services\Reports\Leave\LeaveApplicationsReport;
use App\Services\Reports\Leave\LeaveBalanceReport;
use App\Services\Reports\Leave\LeaveUsageReport;
use App\Services\Reports\Recruitment\AssessmentEvaluationReport;
use App\Services\Reports\Recruitment\CareersPortalReport;
use App\Services\Reports\Recruitment\ConversionReport;
use App\Services\Reports\Recruitment\InterviewReport;
use App\Services\Reports\Recruitment\OfferReport;
use App\Services\Reports\Recruitment\RecruitmentFunnelReport;
use App\Services\Reports\Recruitment\RecruitmentSourceReport;
use App\Services\Reports\Recruitment\RecruitmentWorkloadReport;
use App\Services\Reports\Recruitment\ScreeningReport;
use App\Services\Reports\Recruitment\SelectionReport;
use App\Services\Reports\Recruitment\TimeToFillReport;
use App\Services\Reports\Recruitment\TimeToHireReport;
use App\Services\Reports\Recruitment\VacancyAgingReport;
use App\Services\Reports\Recruitment\VacancyReport;
use App\Services\Reports\UserAccess\AccountMatchingReport;
use App\Services\Reports\UserAccess\RolePermissionsReport;
use App\Services\Reports\UserAccess\UserAccountsReport;
use App\Services\Reports\WorkSchedule\DailyRosterReport;
use App\Services\Reports\WorkSchedule\ScheduleAssignmentsReport;
use InvalidArgumentException;

/**
 * The Core HR report catalog. Access is per category permission.
 */
class ReportRegistry
{
    public const CATEGORIES = [
        'employee' => ['title' => 'Employee Reports', 'permission' => 'report.employee'],
        'work_schedule' => ['title' => 'Work Schedule', 'permission' => 'report.work_schedule'],
        'attendance' => ['title' => 'Attendance', 'permission' => 'report.attendance'],
        'leave' => ['title' => 'Leave', 'permission' => 'report.leave'],
        'recruitment' => ['title' => 'Recruitment', 'permission' => 'report.recruitment'],
        'user_access' => ['title' => 'User & Access', 'permission' => 'report.user_access'],
    ];

    /**
     * @var array<int, class-string<Report>> in catalog order
     */
    public const REPORTS = [
        EmployeeMasterlistReport::class,
        EmployeeDemographicsReport::class,
        HeadcountReport::class,
        EmploymentStatusReport::class,
        DepartmentReport::class,
        JobTitleReport::class,
        NewHiresReport::class,
        EmployeeMovementReport::class,
        ScheduleAssignmentsReport::class,
        DailyRosterReport::class,
        AttendanceLogReport::class,
        AttendanceSummaryReport::class,
        LeaveApplicationsReport::class,
        LeaveUsageReport::class,
        LeaveBalanceReport::class,
        RecruitmentFunnelReport::class,
        VacancyReport::class,
        VacancyAgingReport::class,
        RecruitmentSourceReport::class,
        ScreeningReport::class,
        InterviewReport::class,
        AssessmentEvaluationReport::class,
        SelectionReport::class,
        OfferReport::class,
        TimeToFillReport::class,
        TimeToHireReport::class,
        ConversionReport::class,
        RecruitmentWorkloadReport::class,
        CareersPortalReport::class,
        UserAccountsReport::class,
        RolePermissionsReport::class,
        AccountMatchingReport::class,
    ];

    public static function resolve(string $key): Report
    {
        foreach (self::REPORTS as $class) {
            if ($class::key() === $key) {
                return app($class);
            }
        }

        throw new InvalidArgumentException("Unknown report [{$key}].");
    }

    /**
     * Catalog entries the user may open, grouped by category.
     */
    public static function catalogFor(User $user): array
    {
        $catalog = [];

        foreach (self::CATEGORIES as $category => $meta) {
            if (! $user->can($meta['permission'])) {
                continue;
            }

            $reports = collect(self::REPORTS)
                ->filter(fn ($class) => $class::category() === $category)
                ->map(function ($class) {
                    $report = app($class);

                    return ['key' => $class::key(), 'title' => $report->title(), 'description' => $report->description()];
                })
                ->values()
                ->all();

            $catalog[] = ['key' => $category, 'title' => $meta['title'], 'reports' => $reports];
        }

        return $catalog;
    }
}
