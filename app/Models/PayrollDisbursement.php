<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment of one finalized payslip's net pay.
 *
 *   pending -> exported -> paid | failed      failed -> pending (retry)
 *
 * amount and currency are copied from the payslip and never edited. destination
 * is an encrypted snapshot of the account used. No bank is connected.
 */
class PayrollDisbursement extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_EXPORTED, self::STATUS_PAID, self::STATUS_FAILED];

    public const METHODS = ['bank_transfer', 'cash', 'check', 'other'];

    protected $fillable = [
        'payroll_run_id', 'payroll_run_employee_id', 'employee_bank_account_id', 'amount', 'currency', 'method',
        'status', 'destination', 'reference', 'exported_at', 'paid_at', 'failure_reason', 'updated_by',
    ];

    protected $hidden = ['destination'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'destination' => 'encrypted:array',
            'exported_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'payroll_run_employee_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(EmployeeBankAccount::class, 'employee_bank_account_id');
    }
}
