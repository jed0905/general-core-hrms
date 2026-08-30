<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property string|null $reason
 * @property numeric $total_days
 * @property numeric $total_hours
 * @property string $status
 * @property string $submitted_at
 * @property string|null $approved_at
 * @property string|null $rejected_at
 * @property string|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LeaveStatus|null $currentStatus
 * @property-read \App\Models\Employee $employee
 * @property-read \App\Models\Employee|null $immediate_supervisor
 * @property-read \App\Models\Leave|null $leave
 * @property-read \App\Models\EmployeeLeaveCreditsHistory $leaveCreditsHistory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LeaveApplicationDate> $leaveDates
 * @property-read int|null $leave_dates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LeaveStatus> $leaveStatuses
 * @property-read int|null $leave_statuses_count
 * @property-read \App\Models\EmployeeLeaveCreditsHistory|null $specialLeaveCredit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication visibleTo($user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereLeaveTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereRejectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereTotalDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereTotalHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplication whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LeaveApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'current_status_id',
        'is_restored',
        'leave_id',
        'special_leave_credit_id',
        'location_within_philippines',
        'location_abroad',
        'in_hospital',
        'out_hospital',
        'illness',
        'study_leave_application',
        'other_purposes',
        'no_of_days',
        'from',
        'to',
        'commutation',
        'credits',
        'cancellation_reason',
        'late_filing_reason',
        'remarks',
        'revoked',
    ];

    public function currentStatus()
    {
        return $this->belongsTo(LeaveStatus::class, 'current_status_id');
    }

    public function leaveStatuses()
    {
        return $this->hasMany(LeaveStatus::class, 'leave_application_id');
    }

    public function leaveDates()
    {
        return $this->hasMany(LeaveApplicationDate::class, 'leave_application_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leave()
    {
        return $this->belongsTo(Leave::class);
    }

    public function immediate_supervisor()
    {
        return $this->belongsTo(Employee::class, 'immediate_supervisor_id');
    }

    public function specialLeaveCredit()
    {
        return $this->belongsTo(EmployeeLeaveCreditsHistory::class, 'special_leave_credit_id', 'id');
    }

    /* Leave Credits History */
    public function leaveCreditsHistory()
    {
        return $this->belongsTo(EmployeeLeaveCreditsHistory::class, 'employee_id', 'employee_id');
    }


    // public function scopeVisibleTo($query, $user)
    // {
    //     $employee = $user->employee;
    //     $employeeId = $employee?->id;

    //     $headDesignations = [
    //         'University President',
    //         'Chancellor',
    //         'Executive Director'
    //     ];

    //     return $query->where(function ($q) use ($user, $employee, $employeeId, $headDesignations) {

    //         // Superadmin sees all
    //         if ($user->hasRole('superadmin')) {
    //             return;
    //         }

    //         // HR Staff sees everything past recommendation
    //         if ($user->hasAnyRole(['hr_director', 'campus_hr', 'campus_hr_staff'])) {
    //             $q->orWhereHas('currentStatus', function ($statusQuery) {
    //                 $statusQuery->whereIn('status', ['for approval', 'for disapproval', 'certified', 'approved', 'disapproved']);
    //             });
    //         }

    //         // Immediate Supervisor sees pending leaves from subordinates
    //         if ($employeeId) {
    //             $q->orWhereHas('currentStatus', function ($statusQuery) {
    //                 $statusQuery->where('status', 'pending');
    //             })
    //                 ->whereHas('employee', function ($e) use ($employeeId) {
    //                     $e->where('immediate_supervisor_id', $employeeId);
    //                 });
    //         }

    //         // Head of Operating Unit sees certified, approved, rejected leaves
    //         if (
    //             $employee &&
    //             $employee->employeeDesignations()
    //                 ->whereHas('designation', function ($d) use ($headDesignations) {
    //                     $d->whereIn('name', $headDesignations);
    //                 })
    //                 ->exists()
    //         ) {
    //             $q->orWhereHas('currentStatus', function ($statusQuery) {
    //                 $statusQuery->whereIn('status', ['certified', 'approved', 'disapproved']);
    //             });
    //         }

    //         // Default: employee sees own leave
    //         if ($employeeId) {
    //             $q->orWhere('employee_id', $employeeId);
    //         }
    //     });
    // }

    public function scopeVisibleTo($query, $user)
    {

        $employee = $user->employee;
        $employeeId = $employee?->id;

        // dd($employee->operating_unit_id);

        $headDesignations = [
            'University President',
            'Chancellor',
            'Executive Director'
        ];

        $query->withSum('leaveDates as total_credits', 'credits');

        return $query->withCount('leaveDates') // 👈 needed for 30-day rule
            ->where(function ($q) use ($user, $employee, $employeeId, $headDesignations) {

                // ✅ Superadmin sees all
                if ($user->hasRole('superadmin')) {
                    return $q;
                }

                // ✅ UNIVERSITY PRESIDENT LOGIC (override others)
                if (
                    $employee &&
                    $employee->employeeDesignations()
                        ->whereHas('designation', function ($d) {
                            $d->where('name', 'University President');
                        })
                        ->exists()
                ) {
                    $operatingUnitId = $employee->operating_unit_id;

                    $q->where(function ($presQ) use ($employeeId, $operatingUnitId) {

                        // 1. Direct subordinates
                        if ($employeeId) {
                            $presQ->orWhereHas('employee', function ($e) use ($employeeId) {
                                $e->where('immediate_supervisor_id', $employeeId);
                            });
                        }

                        // 2. Total credits >= 30
                        $presQ->orWhereRaw('(
            SELECT COALESCE(SUM(lad.credits), 0)
            FROM leave_application_dates lad
            WHERE lad.leave_application_id = leave_applications.id
        ) >= 30');

                        // 3. Same visibility as Head of Operating Unit
                        //    for certified / approved / disapproved applications
                        if ($operatingUnitId) {
                            $presQ->orWhere(function ($headQuery) use ($operatingUnitId) {
                                $headQuery
                                    ->whereHas('currentStatus', function ($statusQuery) {
                                        $statusQuery->whereIn('status', [
                                            'certified',
                                            'approved',
                                            'disapproved',
                                        ]);
                                    })
                                    ->whereHas('employee', function ($employeeQuery) use ($operatingUnitId) {
                                        $employeeQuery->where(
                                            'operating_unit_id',
                                            $operatingUnitId
                                        );
                                    });
                            });
                        }
                    });

                    return;
                }

                // ✅ HR Staff
                if ($user->hasRole('hr_director')) {
                    $q->orWhereHas('currentStatus', function ($statusQuery) {
                        $statusQuery->whereIn('status', [
                            'for approval',
                            'for disapproval',
                            'certified',
                            'approved',
                            'disapproved',
                        ]);
                    });
                }

                if ($user->hasAnyRole(['campus_hr', 'campus_hr_staff'])) {

                    $operatingUnitId = $employee->operating_unit_id;

                    $q->orWhere(function ($hrQuery) use ($operatingUnitId) {
                        $hrQuery
                            ->whereHas('currentStatus', function ($statusQuery) {
                                $statusQuery->whereIn('status', [
                                    'for approval',
                                    'for disapproval',
                                    'certified',
                                    'approved',
                                    'disapproved',
                                ]);
                            })
                            ->whereHas('employee', function ($employeeQuery) use ($operatingUnitId) {
                                $employeeQuery->where('operating_unit_id', $operatingUnitId);
                            });
                    });
                }

                // ✅ Immediate Supervisor (ONLY < 30 days ideally, but keeping your logic)
                if ($employeeId) {
                    $q->orWhereHas('currentStatus', function ($statusQuery) {
                        $statusQuery->whereIn('status', [
                            'pending',
                            'for approval',
                            'for disapproval',
                            'certified',
                            'approved',
                            'disapproved'
                        ]);
                    })
                        ->whereHas('employee', function ($e) use ($employeeId) {
                            $e->where('immediate_supervisor_id', $employeeId);
                        });
                }

                // ✅ Head of Operating Unit (excluding President logic already handled)
                if (
                    $employee &&
                    $employee->employeeDesignations()
                        ->whereHas('designation', function ($d) use ($headDesignations) {
                        $d->whereIn('name', $headDesignations);
                    })
                        ->exists()
                ) {
                    $operatingUnitId = $employee->operating_unit_id;

                    $q->orWhere(function ($headQuery) use ($operatingUnitId) {
                        $headQuery
                            ->whereHas('currentStatus', function ($statusQuery) {
                                $statusQuery->whereIn('status', [
                                    'certified',
                                    'approved',
                                    'disapproved',
                                ]);
                            })
                            ->whereHas('employee', function ($employeeQuery) use ($operatingUnitId) {
                                $employeeQuery->where('operating_unit_id', $operatingUnitId);
                            });
                    });
                }
            });
    }
}
