<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 unit of base_currency = rate units of quote_currency, as published by source on rate_date.
 */
class ExchangeRate extends Model
{
    protected $fillable = ['base_currency', 'quote_currency', 'rate', 'rate_date', 'source', 'created_by'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'rate_date' => 'date:Y-m-d',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
