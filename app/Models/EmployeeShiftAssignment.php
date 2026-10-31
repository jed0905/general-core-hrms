<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster entry: the shift (or rest day) an employee works on one date,
 * overriding their weekly Time-module schedule for that date.
 */
class EmployeeShiftAssignment extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'shift_id', 'is_rest_day', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'is_rest_day' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
