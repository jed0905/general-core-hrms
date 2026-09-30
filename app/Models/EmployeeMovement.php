<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employment event (promotion, transfer, resignation, ...).
 *
 * from_* / to_* hold the employee's employment state before and after the
 * movement, and `snapshot` freezes the display names, so the record stays
 * accurate after the employee or the configuration changes again.
 */
class EmployeeMovement extends Model
{
    use HasFactory;

    /** Recorded, waiting for its effective date. */
    public const STATUS_SCHEDULED = 'approved';

    /** Applied to the employee's current record. */
    public const STATUS_EFFECTIVE = 'implemented';

    /** Reversed or withdrawn. */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Employment fields a movement can change (employees columns).
     */
    public const FIELDS = [
        'department_id',
        'job_title_id',
        'employment_status_id',
        'location_id',
        'supervisor_id',
    ];

    protected $fillable = [
        'employee_id',
        'movement_type_id',
        'effective_date',
        'reference_number',
        'reason',
        'remarks',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'implemented_at',
        'from_department_id',
        'to_department_id',
        'from_job_title_id',
        'to_job_title_id',
        'from_employment_status_id',
        'to_employment_status_id',
        'from_location_id',
        'to_location_id',
        'from_supervisor_id',
        'to_supervisor_id',
        'from_status',
        'to_status',
        'changed_fields',
        'snapshot',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'implemented_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'changed_fields' => 'array',
            'snapshot' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EmployeeMovementType::class, 'movement_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isEffective(): bool
    {
        return $this->status === self::STATUS_EFFECTIVE;
    }
}
