<?php

namespace App\Services\Payroll\Contracts;

/**
 * Optional localization capability: which premium time-input category (one of
 * premiumCategories()) a bucket of attended minutes belongs to. Attendance
 * computes buckets by (day type, holiday type, overtime?, night?) without any
 * country rule; the country decides the category. Null = no premium (covered
 * by basic pay or not payable).
 */
interface MapsAttendanceToPremiums
{
    /**
     * @param  string  $dayType  workday | rest_day | holiday | no_schedule
     * @param  string|null  $holidayType  the HRMS holiday type when $dayType is holiday
     */
    public function attendancePremiumCategory(string $dayType, ?string $holidayType, bool $overtime, bool $night): ?string;
}
