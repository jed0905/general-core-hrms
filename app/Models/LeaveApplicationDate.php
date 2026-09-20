<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplicationDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'leave_date',
        'duration_type',
        'hours',
        'day_fraction',
        'start_time',
        'end_time',
        'is_paid',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_application_id' => 'integer',
            'leave_date' => 'date',
            'hours' => 'decimal:2',
            'day_fraction' => 'decimal:4',
            'is_paid' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }
}
