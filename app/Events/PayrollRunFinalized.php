<?php

namespace App\Events;

use App\Models\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after a payroll run is finalized (inside the finalization transaction). */
class PayrollRunFinalized
{
    use Dispatchable;

    public function __construct(public readonly PayrollRun $run) {}
}
