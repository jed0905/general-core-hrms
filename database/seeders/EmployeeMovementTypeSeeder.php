<?php

namespace Database\Seeders;

use App\Models\EmployeeMovementType;
use Illuminate\Database\Seeder;

/**
 * Default movement types, keyed by their stable code.
 *
 * Idempotent: a type is only created when its code doesn't exist yet, so
 * re-running never duplicates types or overwrites an administrator's edits
 * (name, description, fields, status, active flag).
 */
class EmployeeMovementTypeSeeder extends Seeder
{
    public const TYPES = [
        'hiring' => [
            'name' => 'Hiring',
            'description' => 'Start of employment. Records the assignment the employee was hired into; nothing is changed.',
            'affected_fields' => [],
        ],
        'promotion' => [
            'name' => 'Promotion',
            'description' => 'Move to a higher job title.',
            'affected_fields' => ['job_title_id', 'department_id', 'supervisor_id'],
        ],
        'demotion' => [
            'name' => 'Demotion',
            'description' => 'Move to a lower job title.',
            'affected_fields' => ['job_title_id', 'department_id', 'supervisor_id'],
        ],
        'transfer' => [
            'name' => 'Transfer',
            'description' => 'Move to another department and/or location.',
            'affected_fields' => ['department_id', 'location_id', 'supervisor_id'],
        ],
        'reassignment' => [
            'name' => 'Reassignment',
            'description' => 'Change of assignment within the organization.',
            'affected_fields' => ['job_title_id', 'department_id', 'location_id', 'supervisor_id'],
        ],
        'department_transfer' => [
            'name' => 'Department Transfer',
            'description' => 'Move to another department.',
            'affected_fields' => ['department_id', 'supervisor_id'],
        ],
        'job_title_change' => [
            'name' => 'Position / Job Title Change',
            'description' => 'Change of job title without a promotion or demotion.',
            'affected_fields' => ['job_title_id'],
        ],
        'employment_status_change' => [
            'name' => 'Employment Status Change',
            'description' => 'Change of employment status, e.g. probationary to regular.',
            'affected_fields' => ['employment_status_id'],
        ],
        'retirement' => [
            'name' => 'Retirement',
            'description' => 'End of employment by retirement.',
            'affected_fields' => ['employment_status_id'],
            'employee_status' => 'terminated',
        ],
        'resignation' => [
            'name' => 'Resignation',
            'description' => 'End of employment by resignation.',
            'affected_fields' => ['employment_status_id'],
            'employee_status' => 'terminated',
        ],
        'termination' => [
            'name' => 'Termination',
            'description' => 'End of employment by termination.',
            'affected_fields' => ['employment_status_id'],
            'employee_status' => 'terminated',
        ],
        'other' => [
            'name' => 'Other',
            'description' => 'Any other employment change.',
            'affected_fields' => ['job_title_id', 'department_id', 'employment_status_id', 'location_id', 'supervisor_id'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $code => $attributes) {
            EmployeeMovementType::firstOrCreate(['code' => $code], [
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'affected_fields' => $attributes['affected_fields'],
                'employee_status' => $attributes['employee_status'] ?? null,
                'is_active' => true,
            ]);
        }
    }
}
