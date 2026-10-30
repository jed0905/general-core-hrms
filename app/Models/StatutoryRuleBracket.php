<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row of a statutory table (range + amounts/rates). Which columns a method
 * reads is defined by the calculation method. Read-only once the version is verified.
 */
class StatutoryRuleBracket extends Model
{
    public const VALUE_COLUMNS = [
        'range_from', 'range_to', 'base_amount', 'fixed_amount', 'rate', 'excess_over',
        'employee_amount', 'employer_amount', 'employee_rate', 'employer_rate', 'employer_extra_amount',
    ];

    protected $fillable = ['statutory_rule_version_id', 'sequence', ...self::VALUE_COLUMNS];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'range_from' => 'decimal:4',
            'range_to' => 'decimal:4',
            'base_amount' => 'decimal:4',
            'fixed_amount' => 'decimal:4',
            'rate' => 'decimal:8',
            'excess_over' => 'decimal:4',
            'employee_amount' => 'decimal:4',
            'employer_amount' => 'decimal:4',
            'employee_rate' => 'decimal:8',
            'employer_rate' => 'decimal:8',
            'employer_extra_amount' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (StatutoryRuleBracket $bracket) {
            $status = StatutoryRuleVersion::whereKey($bracket->statutory_rule_version_id)->value('verification_status');
            if (in_array($status, StatutoryRuleVersion::LOCKED, true)) {
                throw new LogicException('Brackets of a verified statutory rule version are read-only.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleVersion::class, 'statutory_rule_version_id');
    }
}
