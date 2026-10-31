<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company attendance rules for a legal entity (or the default policy).
 * Contains no statutory values.
 */
class AttendancePolicy extends Model
{
    public const PAIRING_MODES = ['in_out', 'first_last'];

    protected $fillable = [
        'name', 'legal_entity_id', 'is_default', 'late_grace_minutes', 'undertime_grace_minutes', 'pairing_mode',
        'debounce_minutes', 'deduct_scheduled_break', 'overtime_threshold_minutes', 'overtime_requires_approval',
        'count_early_arrival_as_overtime', 'night_start', 'night_end', 'window_before_minutes', 'day_start_time',
        'timezone', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'late_grace_minutes' => 'integer',
            'undertime_grace_minutes' => 'integer',
            'debounce_minutes' => 'integer',
            'deduct_scheduled_break' => 'boolean',
            'overtime_threshold_minutes' => 'integer',
            'overtime_requires_approval' => 'boolean',
            'count_early_arrival_as_overtime' => 'boolean',
            'window_before_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
