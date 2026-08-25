<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'operating_unit_id',
        'is_vsl',
    ];

    protected $casts = [
        'is_vsl' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function operatingUnit()
    {
        return $this->belongsTo(OperatingUnit::class);
    }

    public function employeeDesignations()
    {
        return $this->hasMany(EmployeeDesignation::class);
    }

    public static function getHeadHrmo($operatingUnitId)
    {
        return self::whereIn('name', [
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
        ])
            ->where('operating_unit_id', $operatingUnitId)
            ->with(['employeeDesignations.employee.personalInformation'])
            ->first()
            ?->employeeDesignations
            ->first()
                ?->employee;
    }

    public static function getHeadOperatingUnit($operatingUnitId)
    {
        $designation = self::whereIn('name', [
            'University President',
            'Chancellor',
            'Executive Director',
        ])
            ->where('operating_unit_id', $operatingUnitId)
            ->with(['employeeDesignations.employee.personalInformation'])
            ->first();

        if (!$designation) {
            return null;
        }

        $employeeDesignation = $designation->employeeDesignations->first();

        if (!$employeeDesignation) {
            return null;
        }

        return [
            'employee' => $employeeDesignation->employee,                 // full Employee model
            'designation' => $designation->name
        ];
    }

    public static function getUniversityPresident()
    {
        return self::where(
            'name',
            'University President'
        )
            ->with(['employeeDesignations.employee.personalInformation'])
            ->first()
            ?->employeeDesignations
            ->first()
                ?->employee;
    }
}
