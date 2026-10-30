<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A named scalar of a statutory rule version (a rate, floor, ceiling, multiplier,
 * or a text option). Read-only once the version is verified.
 */
class StatutoryRuleParameter extends Model
{
    protected $fillable = ['statutory_rule_version_id', 'key', 'value', 'text_value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:8'];
    }

    protected static function booted(): void
    {
        $guard = function (StatutoryRuleParameter $parameter) {
            $status = StatutoryRuleVersion::whereKey($parameter->statutory_rule_version_id)->value('verification_status');
            if (in_array($status, StatutoryRuleVersion::LOCKED, true)) {
                throw new LogicException('Parameters of a verified statutory rule version are read-only.');
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
