<?php

namespace App\Models;

use App\Models\OtherInfoSpecialSkills;
use App\Models\PersonalInformation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Employee extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'date_hired',
        'employee_number',
        'biometrics_id',
        'detailed_at',
        'photo',
        'user_id',
        'role_id',
        'department_id',
        'employee_type',
        'position_id',
        'parenthetical_title',
        'job_status_id',
        'salary_step_id',
        'custom_hourly_rate',
        'custom_daily_rate',
        'operating_unit_id',
        'gov_issued_id',
        'gov_id_number',
        'date_issue',
        'place_issue',
        'date_separated',
        'separation_reason',
        'immediate_supervisor_id',
        'immediate_supervisor_designation',
        'higher_supervisor_id',
        'higher_supervisor_designation',
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function operatingUnit()
    {
        return $this->belongsTo(OperatingUnit::class);
    }

    public function head($date = null)
    {
        $date = $date
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();

        return self::whereHas('employeeDesignations', function ($q) use ($date) {
            $q->whereDate('assumption_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $date);
                })
                ->whereHas('designation', function ($q) {
                    $q->where('operating_unit_id', $this->operating_unit_id)
                        ->whereIn('name', [
                            'University President',
                            'Chancellor',
                            'Executive Director'
                        ]);
                });
        })
            ->first();
    }

    public function hrHead($date = null)
    {
        $date = $date
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();

        return self::whereHas('employeeDesignations', function ($q) use ($date) {
            $q->whereDate('assumption_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $date);
                })
                ->whereHas('designation', function ($q) {
                    $q->where('operating_unit_id', $this->operating_unit_id)
                        ->whereIn('name', [
                            'Head, Human Resource Management',
                            'Human Resource Management Head',
                            'HRM Head',
                            'Head, HRM',
                            'HR Head',
                            'Head, Human Resource',
                            'Human Resource Head',
                            'Head, HR',
                            'Human Resource Management Officer',
                            'Director, Human Resource Management Office',
                            'Director, HRM Office',
                            'Director, HR Office',
                            'Director, Human Resource Management',
                            'University Human Resource Management Officer',
                        ]);
                });
        })
            ->first();
    }

    public function detailedAt()
    {
        return $this->belongsTo(OperatingUnit::class, 'detailed_at', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employeeDesignations()
    {
        return $this->hasMany(EmployeeDesignation::class);
    }

    public function biometricIds()
    {
        return $this->hasMany(EmployeeBiometricId::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function jobStatus()
    {
        return $this->belongsTo(JobStatus::class);
    }

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function salaryStep()
    {
        return $this->belongsTo(SalaryStep::class);
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'immediate_supervisor_id');
    }

    public function spouse()
    {
        return $this->hasOne(Spouse::class);
    }

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function personalInformation()
    {
        return $this->hasOne(PersonalInformation::class);
    }

    public function immediateSupervisor()
    {
        return $this->belongsTo(Employee::class, 'immediate_supervisor_id');
    }

    public function higherSupervisor()
    {
        return $this->belongsTo(Employee::class, 'higher_supervisor_id');
    }

    public function familyBackground()
    {
        return $this->hasOne(FamilyBackground::class);
    }

    public function elementary()
    {
        return $this->hasMany(Elementary::class);
    }

    public function secondary()
    {
        return $this->hasMany(Secondary::class);
    }

    public function vocational()
    {
        return $this->hasMany(Vocational::class);
    }

    public function college()
    {
        return $this->hasMany(College::class);
    }

    public function graduateStudy()
    {
        return $this->hasMany(GraduateStudy::class);
    }

    public function workExperience()
    {
        return $this->hasMany(WorkExperience::class, 'employee_id', 'id');
    }

    public function voluntaryWork()
    {
        return $this->hasMany(VoluntaryWork::class, 'employee_id', 'id');
    }

    public function civilServiceEligibility()
    {
        return $this->hasMany(CivilServiceEligibility::class, 'employee_id', 'id');
    }

    public function learningAndDevelopment()
    {
        return $this->hasMany(LearningDevelopment::class, 'employee_id', 'id');
    }

    public function otherInfoOrganization()
    {
        return $this->hasMany(OtherInfoOrganization::class, 'employee_id', 'id');
    }

    public function otherInfoNonAcademicDistinction()
    {
        return $this->hasMany(OtherInfoNonAcademicDistinction::class, 'employee_id', 'id');
    }

    public function otherInfoSpecialSkills()
    {
        return $this->hasMany(OtherInfoSpecialSkills::class, 'employee_id', 'id');
    }

    public function leaveCreditsHistory()
    {
        return $this->hasMany(EmployeeLeaveCreditsHistory::class, 'employee_id', 'id');
    }
    public function references()
    {
        return $this->hasMany(EmployeeReference::class, 'employee_id', 'id');
    }

    public function additionalInformation()
    {
        return $this->hasOne(EmployeeAdditionalInformation::class, 'employee_id', 'id');
    }

    public function payrollProjectFunds()
    {
        return $this->hasOne(PayrollEmployeeProjectFund::class, 'employee_number', 'employee_number');
    }

    public function payrollAccountInformation()
    {
        return $this->hasMany(PayrollEmployeeAccountInformation::class, 'employee_number', 'id');
    }

    public function employeeLeaveSchedulers()
    {
        return $this->hasMany(EmployeeLeaveScheduler::class, 'employee_id', 'id');
    }

    public function weeklyShiftTemplate()
    {
        return $this->belongsTo(WeeklyShiftTemplate::class, 'work_shift', 'id');
    }

    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class, 'employee_id', 'id');
    }

    // Access daily shift schedules through the relationships
    public function dailyShiftSchedules()
    {
        return $this->hasManyThrough(
            DailyShiftSchedule::class,        // Final model
            WeeklyShiftDay::class,            // Intermediate model
            'weekly_shift_template_id',       // Foreign key on weekly_shift_days
            'id',                             // Foreign key on daily_shift_schedules (default)
            'work_shift',                     // Local key on employees
            'daily_shift_schedule_id'         // Local key on weekly_shift_days
        );
    }

    public function employeeMovements()
    {
        return $this->hasMany(EmployeeMovement::class, 'employee_id', 'id');
    }

    public function isDean()
    {
        return $this->employeeDesignations()
            ->whereHas('designation', function ($q) {
                $q->where('name', 'like', '%Dean%');
            })
            ->exists();
    }

    public function isDepartmentChairperson()
    {
        return $this->employeeDesignations()
            ->whereHas('designation', function ($q) {
                $q->where('name', 'like', '%Chairperson%');
            })
            ->exists();
    }

    public function getSalaryAttribute()
    {
        $today = now();

        // Get the latest active salary schedule
        $schedule = SalarySchedule::where('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            })
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first() ??
            SalarySchedule::where('is_active', true)
                ->orderByDesc('effective_from')
                ->first();
        ;

        // Get the employee's current step number
        $stepNumber = $this->salaryStep->salary_step_no ?? null;

        // Return null if schedule or position is missing
        if (!$schedule || !$this->position || !$stepNumber) {
            return null;
        }

        // Get the salary amount from the matrix
        $amount = SalaryMatrix::where('salary_schedule_id', $schedule->id)
            ->where('salary_grade_id', (int) $this->position->salary_grade_id)
            ->where('step_number', $stepNumber)
            ->value('amount');

        return $amount !== null ? (float) $amount : null;
    }

    public function attendanceLogs()
    {
        return $this->hasManyThrough(
            EmployeeAttendanceLog::class,
            EmployeeBiometricId::class,
            'employee_id',     // FK on biometric table
            'employee_id',     // FK on logs table (biometric_id)
            'id',              // Employee PK
            'biometric_id'     // Biometric PK
        );
    }
}
