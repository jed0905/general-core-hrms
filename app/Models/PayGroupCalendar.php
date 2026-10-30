<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One pay group's payroll calendar for a year (table payroll_calendars).
 * Named PayGroupCalendar because legacy code imports a missing PayrollCalendar model.
 */
class PayGroupCalendar extends Model
{
    protected $table = 'payroll_calendars';

    protected $fillable = ['pay_group_id', 'calendar_year', 'name', 'created_by'];

    protected function casts(): array
    {
        return ['calendar_year' => 'integer'];
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class, 'payroll_calendar_id')->orderBy('period_number');
    }
}
