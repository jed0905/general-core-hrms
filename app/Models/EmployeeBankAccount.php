<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salary payment destination. Account number and IBAN are encrypted and hidden
 * from serialization; account_last4 is for display.
 */
class EmployeeBankAccount extends Model
{
    protected $fillable = [
        'employee_id', 'bank_name', 'bank_identifier', 'account_name', 'account_number', 'account_last4',
        'iban', 'currency', 'is_primary', 'is_active', 'created_by',
    ];

    protected $hidden = ['account_number', 'iban'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'iban' => 'encrypted',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
