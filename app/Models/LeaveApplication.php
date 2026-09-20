<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'reason',
        'total_days',
        'total_hours',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'total_days' => 'decimal:2',
            'total_hours' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function dates()
    {
        return $this->hasMany(LeaveApplicationDate::class);
    }

    public function attachments()
    {
        return $this->hasMany(LeaveApplicationAttachment::class);
    }

    public function approvals()
    {
        return $this->hasMany(LeaveApplicationApproval::class);
    }
}
