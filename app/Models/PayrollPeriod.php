<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'payroll_calendar_id', 'pay_group_id', 'period_number', 'period_start', 'period_end',
        'cutoff_date', 'pay_date', 'tax_year', 'status',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'cutoff_date' => 'date:Y-m-d',
            'pay_date' => 'date:Y-m-d',
            'tax_year' => 'integer',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(PayGroupCalendar::class, 'payroll_calendar_id');
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function label(): string
    {
        return $this->period_start->toDateString().' to '.$this->period_end->toDateString();
    }
}
