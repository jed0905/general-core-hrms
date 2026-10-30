<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ISO 4217 currency with its minor units (the rounding precision payroll uses).
 */
class Currency extends Model
{
    protected $fillable = ['code', 'name', 'minor_units', 'is_active'];

    protected function casts(): array
    {
        return [
            'minor_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
