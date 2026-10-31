<?php

namespace App\Services\Payroll;

use App\Events\PayrollRunFinalized;
use App\Models\EmploymentAssignment;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollRunEvent;
use App\Models\PayrollRunException;
use App\Models\PayrollRunLine;
use App\Models\StatutoryRuleVersion;
use App\Models\User;
use App\Services\NumberSequenceService;
use App\Services\Payroll\Calculation\PayrollCalculator;
use App\Services\Payroll\Rules\StatutoryRuleResolver;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll run lifecycle. Every transition locks the run row, re-checks its state
 * inside the transaction and appends a payroll_run_events row. Separation of
 * duties is enforced here as well as in PayrollRunPolicy:
 * calculator != reviewer != approver, calculator != finalizer.
 */
class PayrollRunService
{
    public function __construct(
        protected PayrollCalculator $calculator,
        protected NumberSequenceService $numbers,
        protected PayrollScope $scope,
        protected DisbursementService $disbursements,
    ) {}

    public function getPaginatedRuns(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->scope->apply(PayrollRun::query(), $user)
            ->with(['legalEntity:id,code,name,country_code', 'payGroup:id,code,name,pay_frequency', 'period:id,period_start,period_end,pay_date'])
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['run_type'] ?? null, fn (Builder $q, $type) => $q->where('run_type', $type))
            ->when($filters['legal_entity_id'] ?? null, fn (Builder $q, $id) => $q->where('legal_entity_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $q->where('run_number', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * A regular or supplemental run for an open period. At most one active
     * regular run per period (also enforced by a unique index).
     */
    public function createRun(array $data, User $user): PayrollRun
    {
        return DB::transaction(function () use ($data, $user) {
            $period = PayrollPeriod::with('payGroup.legalEntity')->lockForUpdate()->findOrFail($data['payroll_period_id']);
            $payGroup = $period->payGroup;
            $type = $data['run_type'] ?? PayrollRun::TYPE_REGULAR;

            if (! $this->scope->allows($user, $payGroup->legal_entity_id)) {
                throw ValidationException::withMessages(['payroll_period_id' => 'You do not administer payroll for this legal entity.']);
            }
            if ($period->status !== PayrollPeriod::STATUS_OPEN) {
                throw ValidationException::withMessages(['payroll_period_id' => 'This payroll period is closed.']);
            }
            if (! $payGroup->is_active) {
                throw ValidationException::withMessages(['payroll_period_id' => 'This pay group is inactive.']);
            }
            if ($type === PayrollRun::TYPE_REGULAR && PayrollRun::where('payroll_period_id', $period->id)
                ->where('run_type', PayrollRun::TYPE_REGULAR)
                ->where('status', '<>', PayrollRun::STATUS_CANCELLED)
                ->exists()) {
                throw ValidationException::withMessages(['payroll_period_id' => 'A regular payroll run already exists for this period. Use a supplemental run for corrections.']);
            }

            try {
                $run = PayrollRun::create([
                    'run_number' => $this->numbers->next('payroll_run_number'),
                    'legal_entity_id' => $payGroup->legal_entity_id,
                    'pay_group_id' => $payGroup->id,
                    'payroll_period_id' => $period->id,
                    'run_type' => $type,
                    'status' => PayrollRun::STATUS_DRAFT,
                    'currency' => $payGroup->pay_currency,
                    'description' => $data['description'] ?? null,
                    'created_by' => $user->id,
                ]);
            } catch (QueryException $e) {
                throw ValidationException::withMessages(['payroll_period_id' => 'A regular payroll run already exists for this period.']);
            }

            $this->event($run, 'created', null, PayrollRun::STATUS_DRAFT, $user);

            return $run;
        });
    }

    /** Assignments the run would pay (before exclusions). */
    public function eligibleAssignments(PayrollRun $run): Collection
    {
        $period = $run->period;
        $query = EmploymentAssignment::with('employee')
            ->where('pay_group_id', $run->pay_group_id)
            ->overlapping($period->period_start->toDateString(), $period->period_end->toDateString());

        if ($run->run_type === PayrollRun::TYPE_SUPPLEMENTAL) {
            $query->whereIn('employee_id', PayrollAdjustment::where('payroll_period_id', $period->id)
                ->where('status', PayrollAdjustment::STATUS_APPROVED)
                ->select('employee_id'));
        }

        return $query->orderBy('employee_id')->get();
    }

    public function excludeEmployee(PayrollRun $run, int $assignmentId, string $reason, User $user): void
    {
        DB::transaction(function () use ($run, $assignmentId, $reason, $user) {
            $run = $this->lock($run, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_CALCULATED]);
            $assignment = $this->eligibleAssignments($run)->firstWhere('id', $assignmentId)
                ?? throw ValidationException::withMessages(['employment_assignment_id' => 'This employee is not part of this run.']);

            $payslip = PayrollRunEmployee::firstOrNew(['payroll_run_id' => $run->id, 'employment_assignment_id' => $assignment->id]);
            if ($payslip->exists) {
                $payslip->lines()->delete();
                $payslip->exceptions()->delete();
            }
            $payslip->fill([
                'employee_id' => $assignment->employee_id,
                'status' => PayrollRunEmployee::STATUS_EXCLUDED,
                'exclusion_reason' => $reason,
                'currency' => $run->currency,
                'snapshot' => null,
                ...array_fill_keys(PayrollRunEmployee::TOTAL_COLUMNS, '0'),
            ])->save();

            $this->refreshTotals($run);
            $this->event($run, 'employee_excluded', $run->status, $run->status, $user, "{$assignment->employee?->employee_number}: {$reason}");
        });
    }

    public function includeEmployee(PayrollRun $run, int $assignmentId, User $user): void
    {
        DB::transaction(function () use ($run, $assignmentId, $user) {
            $run = $this->lock($run, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_CALCULATED]);
            $payslip = PayrollRunEmployee::where('payroll_run_id', $run->id)
                ->where('employment_assignment_id', $assignmentId)
                ->where('status', PayrollRunEmployee::STATUS_EXCLUDED)
                ->firstOrFail();
            $payslip->delete();
            $from = $run->status;

            // Results are stale once someone is added back: recalculation is required.
            if ($run->status === PayrollRun::STATUS_CALCULATED) {
                $run->forceFill(['status' => PayrollRun::STATUS_DRAFT])->save();
            }
            $this->event($run, 'employee_included', $from, $run->status, $user);
        });
    }

    /**
     * (Re)calculate every included employee. Previous results of this
     * (non-final) run are replaced; exclusions are kept.
     */
    public function calculate(PayrollRun $run, User $user): PayrollRun
    {
        return DB::transaction(function () use ($run, $user) {
            $run = $this->lock($run, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_CALCULATED]);
            if ($run->run_type === PayrollRun::TYPE_REVERSAL) {
                throw ValidationException::withMessages(['run' => 'Reversal runs are generated from the original run and are not recalculated.']);
            }

            $run->load(['period', 'payGroup', 'legalEntity']);
            $from = $run->status;

            PayrollRunLine::where('payroll_run_id', $run->id)->delete();
            PayrollRunException::where('payroll_run_id', $run->id)->delete();
            PayrollRunEmployee::where('payroll_run_id', $run->id)->where('status', PayrollRunEmployee::STATUS_INCLUDED)->get()->each->delete();

            $excluded = PayrollRunEmployee::where('payroll_run_id', $run->id)->pluck('employment_assignment_id')->all();
            $usesUnverified = false;
            $now = now();

            foreach ($this->eligibleAssignments($run) as $assignment) {
                if (in_array($assignment->id, $excluded, true)) {
                    continue;
                }

                $rules = new StatutoryRuleResolver(allowDrafts: true);
                $result = $this->calculator->calculate($run, $assignment, $rules);
                $usesUnverified = $usesUnverified || $rules->usesUnverified();

                $payslip = PayrollRunEmployee::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $assignment->employee_id,
                    'employment_assignment_id' => $assignment->id,
                    'status' => PayrollRunEmployee::STATUS_INCLUDED,
                    'currency' => $run->currency,
                    ...$result->totals,
                    'snapshot' => $result->snapshot,
                    'calculated_at' => $now,
                ]);

                foreach ($result->lines as $index => $line) {
                    PayrollRunLine::create([
                        'payroll_run_id' => $run->id,
                        'payroll_run_employee_id' => $payslip->id,
                        'sequence' => $index + 1,
                        'line_class' => $line->lineClass,
                        'code' => $line->code,
                        'description' => $line->description,
                        'pay_component_id' => $line->payComponentId,
                        'quantity' => $line->quantity,
                        'rate' => $line->rate,
                        'multiplier' => $line->multiplier,
                        'amount' => $line->amount,
                        'currency' => $run->currency,
                        'tax_treatment' => $line->taxTreatment,
                        'contribution_treatment' => $line->contributionTreatment,
                        'statutory_rule_version_id' => $line->ruleVersionId,
                        'payroll_adjustment_id' => $line->adjustmentId,
                        'inputs' => $line->inputs ?: null,
                    ]);
                }

                foreach ($result->exceptions as $exception) {
                    PayrollRunException::create([
                        'payroll_run_id' => $run->id,
                        'payroll_run_employee_id' => $payslip->id,
                        'employee_id' => $assignment->employee_id,
                        ...$exception,
                    ]);
                }
            }

            if ($usesUnverified) {
                PayrollRunException::create([
                    'payroll_run_id' => $run->id,
                    'severity' => PayrollRunException::SEVERITY_WARNING,
                    'code' => 'UNVERIFIED_RULES',
                    'message' => 'This run used statutory rule versions that are not verified. It can be reviewed as a preview but cannot be finalized until those versions are verified and the run is recalculated.',
                ]);
            }

            $run->forceFill([
                'status' => PayrollRun::STATUS_CALCULATED,
                'engine_version' => config('payroll.engine_version'),
                'uses_unverified_rules' => $usesUnverified,
                'calculated_at' => $now,
                'calculated_by' => $user->id,
                'reviewed_at' => null, 'reviewed_by' => null, 'approved_at' => null, 'approved_by' => null,
            ])->save();
            $this->refreshTotals($run);
            $this->event($run, 'calculated', $from, PayrollRun::STATUS_CALCULATED, $user);

            return $run->fresh();
        });
    }

    public function review(PayrollRun $run, User $user, ?string $remarks = null): PayrollRun
    {
        return $this->transition($run, $user, [PayrollRun::STATUS_CALCULATED], PayrollRun::STATUS_REVIEWED, 'reviewed', $remarks, function (PayrollRun $run) use ($user) {
            $this->assertNoErrors($run);
            if ((int) $run->calculated_by === $user->id) {
                throw ValidationException::withMessages(['run' => 'The person who calculated a run cannot review it.']);
            }
            $run->forceFill(['reviewed_at' => now(), 'reviewed_by' => $user->id]);
        });
    }

    public function approve(PayrollRun $run, User $user, ?string $remarks = null): PayrollRun
    {
        return $this->transition($run, $user, [PayrollRun::STATUS_REVIEWED], PayrollRun::STATUS_APPROVED, 'approved', $remarks, function (PayrollRun $run) use ($user) {
            $this->assertNoErrors($run);
            if (in_array($user->id, [(int) $run->calculated_by, (int) $run->reviewed_by], true)) {
                throw ValidationException::withMessages(['run' => 'The person who calculated or reviewed a run cannot approve it.']);
            }
            $run->forceFill(['approved_at' => now(), 'approved_by' => $user->id]);
        });
    }

    /** Send a calculated/reviewed/approved run back to draft for correction. */
    public function returnToDraft(PayrollRun $run, User $user, string $remarks): PayrollRun
    {
        return $this->transition($run, $user, PayrollRun::RETURNABLE, PayrollRun::STATUS_DRAFT, 'returned', $remarks, function (PayrollRun $run) {
            if ($run->run_type === PayrollRun::TYPE_REVERSAL) {
                throw ValidationException::withMessages(['run' => 'A reversal run cannot be returned to draft; cancel it instead.']);
            }
            $run->forceFill(['reviewed_at' => null, 'reviewed_by' => null, 'approved_at' => null, 'approved_by' => null]);
        });
    }

    public function cancel(PayrollRun $run, User $user, string $remarks): PayrollRun
    {
        return $this->transition($run, $user, [PayrollRun::STATUS_DRAFT, ...PayrollRun::RETURNABLE], PayrollRun::STATUS_CANCELLED, 'cancelled', $remarks, function (PayrollRun $run) use ($user) {
            $run->forceFill(['cancelled_at' => now(), 'cancelled_by' => $user->id]);
        });
    }

    /**
     * Lock the results: assign payslip numbers, mark paid adjustments applied,
     * open disbursement records. Only verified statutory rules may be used.
     */
    public function finalize(PayrollRun $run, User $user, ?string $remarks = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $user, $remarks) {
            $run = $this->lock($run, [PayrollRun::STATUS_APPROVED]);
            $this->assertNoErrors($run);

            if ((int) $run->calculated_by === $user->id) {
                throw ValidationException::withMessages(['run' => 'The person who calculated a run cannot finalize it.']);
            }
            $unverified = StatutoryRuleVersion::whereIn('id', PayrollRunLine::where('payroll_run_id', $run->id)->whereNotNull('statutory_rule_version_id')->select('statutory_rule_version_id'))
                ->whereNotIn('verification_status', StatutoryRuleVersion::LOCKED)
                ->exists();
            if ($run->uses_unverified_rules || $unverified) {
                throw ValidationException::withMessages(['run' => 'This run used statutory rules that are not verified. Verify them and recalculate before finalizing.']);
            }
            if ($run->run_type === PayrollRun::TYPE_REVERSAL && ! $run->reversesRun()->where('status', PayrollRun::STATUS_FINALIZED)->exists()) {
                throw ValidationException::withMessages(['run' => 'The run being reversed is not finalized.']);
            }

            $payslips = PayrollRunEmployee::where('payroll_run_id', $run->id)
                ->where('status', PayrollRunEmployee::STATUS_INCLUDED)
                ->orderBy('id')
                ->get();
            foreach ($payslips as $payslip) {
                $payslip->update(['payslip_number' => $this->numbers->next('payslip_number')]);
            }

            PayrollAdjustment::whereIn('id', PayrollRunLine::where('payroll_run_id', $run->id)->whereNotNull('payroll_adjustment_id')->select('payroll_adjustment_id'))
                ->where('status', PayrollAdjustment::STATUS_APPROVED)
                ->get()
                ->each(fn (PayrollAdjustment $a) => $a->update(['status' => PayrollAdjustment::STATUS_APPLIED, 'applied_run_id' => $run->id]));

            if ($run->run_type !== PayrollRun::TYPE_REVERSAL) {
                $this->disbursements->openForRun($run, $payslips);
            }

            $run->forceFill(['status' => PayrollRun::STATUS_FINALIZED, 'finalized_at' => now(), 'finalized_by' => $user->id])->save();
            $this->event($run, 'finalized', PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED, $user, $remarks);
            PayrollRunFinalized::dispatch($run);

            return $run->fresh();
        });
    }

    /**
     * A reversal run that negates every line of a finalized run, line for line.
     * It goes through review, approval and finalization like any run. Corrected
     * amounts are then paid with adjustments (supplemental or next regular run).
     */
    public function reverse(PayrollRun $original, User $user, string $reason): PayrollRun
    {
        return DB::transaction(function () use ($original, $user, $reason) {
            $original = PayrollRun::whereKey($original->id)->lockForUpdate()->firstOrFail();

            if (! $original->isFinalized() || $original->run_type === PayrollRun::TYPE_REVERSAL) {
                throw ValidationException::withMessages(['run' => 'Only a finalized regular or supplemental run can be reversed.']);
            }
            if (PayrollRun::where('reverses_run_id', $original->id)->where('status', '<>', PayrollRun::STATUS_CANCELLED)->exists()) {
                throw ValidationException::withMessages(['run' => 'This run already has a reversal.']);
            }

            $now = now();
            $reversal = PayrollRun::create([
                'run_number' => $this->numbers->next('payroll_run_number'),
                'legal_entity_id' => $original->legal_entity_id,
                'pay_group_id' => $original->pay_group_id,
                'payroll_period_id' => $original->payroll_period_id,
                'run_type' => PayrollRun::TYPE_REVERSAL,
                'status' => PayrollRun::STATUS_CALCULATED,
                'currency' => $original->currency,
                'reverses_run_id' => $original->id,
                'description' => 'Reversal of '.$original->run_number.': '.$reason,
                'engine_version' => $original->engine_version,
                'created_by' => $user->id,
                'calculated_at' => $now,
                'calculated_by' => $user->id,
            ]);

            $payslips = PayrollRunEmployee::with('lines')
                ->where('payroll_run_id', $original->id)
                ->where('status', PayrollRunEmployee::STATUS_INCLUDED)
                ->get();

            foreach ($payslips as $payslip) {
                $copy = PayrollRunEmployee::create([
                    'payroll_run_id' => $reversal->id,
                    'employee_id' => $payslip->employee_id,
                    'employment_assignment_id' => $payslip->employment_assignment_id,
                    'status' => PayrollRunEmployee::STATUS_INCLUDED,
                    'currency' => $payslip->currency,
                    ...collect(PayrollRunEmployee::TOTAL_COLUMNS)->mapWithKeys(fn ($c) => [$c => Decimal::format(Decimal::neg((string) $payslip->{$c}), 4)])->all(),
                    'snapshot' => ['reverses_payslip_id' => $payslip->id, 'reverses_payslip_number' => $payslip->payslip_number, 'original' => $payslip->snapshot],
                    'calculated_at' => $now,
                ]);

                foreach ($payslip->lines as $line) {
                    PayrollRunLine::create([
                        ...$line->only(['sequence', 'line_class', 'code', 'description', 'pay_component_id', 'quantity', 'rate', 'multiplier', 'currency', 'tax_treatment', 'contribution_treatment', 'statutory_rule_version_id', 'inputs']),
                        'payroll_run_id' => $reversal->id,
                        'payroll_run_employee_id' => $copy->id,
                        'amount' => Decimal::format(Decimal::neg((string) $line->amount), 4),
                        'reverses_line_id' => $line->id,
                    ]);
                }
            }

            $this->refreshTotals($reversal);
            $this->event($reversal, 'created', null, PayrollRun::STATUS_CALCULATED, $user, $reason);
            $this->event($original, 'reversal_created', $original->status, $original->status, $user, $reversal->run_number.': '.$reason);

            return $reversal->fresh();
        });
    }

    /* --------------------------------------------------------------- helpers */

    protected function transition(PayrollRun $run, User $user, array $allowed, string $to, string $event, ?string $remarks, callable $mutate): PayrollRun
    {
        return DB::transaction(function () use ($run, $user, $allowed, $to, $event, $remarks, $mutate) {
            $run = $this->lock($run, $allowed);
            $from = $run->status;
            $mutate($run);
            $run->forceFill(['status' => $to])->save();
            $this->event($run, $event, $from, $to, $user, $remarks);

            return $run->fresh();
        });
    }

    /** Re-read the run under a row lock and check its state inside the transaction. */
    protected function lock(PayrollRun $run, array $allowedStatuses): PayrollRun
    {
        $locked = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages(['run' => "This action isn't possible while the run is {$locked->status}."]);
        }

        return $locked;
    }

    protected function assertNoErrors(PayrollRun $run): void
    {
        if ($run->hasBlockingExceptions()) {
            throw ValidationException::withMessages(['run' => 'Resolve the calculation errors and recalculate first.']);
        }
    }

    /** Run totals = sums of the included payslips' rounded totals. */
    protected function refreshTotals(PayrollRun $run): void
    {
        $payslips = PayrollRunEmployee::where('payroll_run_id', $run->id)->where('status', PayrollRunEmployee::STATUS_INCLUDED)->get();
        $sum = fn (string $column) => Decimal::format(Decimal::sum($payslips->map(fn ($p) => (string) $p->{$column})), 4);

        $run->forceFill([
            'gross_total' => $sum('gross_pay'),
            'employee_deduction_total' => $sum('employee_deductions'),
            'tax_total' => $sum('tax'),
            'net_total' => $sum('net_pay'),
            'employer_contribution_total' => $sum('employer_contributions'),
            'employer_cost_total' => $sum('employer_cost'),
            'employee_count' => $payslips->count(),
        ])->save();
    }

    protected function event(PayrollRun $run, string $event, ?string $from, ?string $to, User $user, ?string $remarks = null): void
    {
        PayrollRunEvent::create([
            'payroll_run_id' => $run->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $user->id,
            'remarks' => $remarks,
            'occurred_at' => now(),
        ]);
    }
}
