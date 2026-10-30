<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Country-specific payroll attributes of an employee (statutory IDs, withholding
 * status, ...). The fields are declared and validated by the country's
 * localization; they are encrypted at rest and never logged.
 */
class EmployeeStatutoryProfile extends Model
{
    protected $fillable = ['employee_id', 'country_code', 'fields', 'effective_from', 'effective_to', 'created_by'];

    protected $hidden = ['fields'];

    protected function casts(): array
    {
        return [
            'fields' => 'encrypted:array',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
