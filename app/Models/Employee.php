<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Employee extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'photo',

        'employee_number',
        'emp_last_name',
        'emp_first_name',
        'emp_middle_name',
        'emp_suffix',
        'emp_birthday',

        'emp_nationality_id',
        'emp_sex',
        'emp_marital_status',

        'street1',
        'street2',
        'city',
        'province',
        'zip_code',
        'country_id',
        'home_telephone_no',
        'mobile_no',
        'work_no',
        'work_email',
        'other_email',

        'e_signature_path',

        'joined_date',
        'job_title_id',
        'department_id',
        'location_id',
        'employment_status_id',
        'status',

        'supervisor_id',
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employmentStatus()
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function supervisor()
    {
        return $this->hasOne(Employee::class, 'supervisor_id', 'id');
    }

    public function nationality()
    {
        return $this->belongsTo(Nationality::class, 'emp_nationality_id', 'id');
    }

    public function education()
    {
        return $this->hasMany(Education::class);
    }

    public function workExperience()
    {
        return $this->hasMany(WorkExperience::class);
    }

    public function workSchedule()
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }


}
