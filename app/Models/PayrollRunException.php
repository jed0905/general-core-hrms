<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A problem found while calculating a run. Errors block review, approval and
 * finalization; warnings are shown for review only.
 */
class PayrollRunException extends Model
{
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    protected $fillable = ['payroll_run_id', 'payroll_run_employee_id', 'employee_id', 'severity', 'code', 'message'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
