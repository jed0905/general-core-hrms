<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeStatutoryProfile;
use App\Models\EmployeeTaxResidency;
use App\Models\EmploymentAssignment;
use App\Models\PayGroup;
use App\Models\User;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\Support\CurrencyPrecision;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * An employee's payroll profile: employment assignments (jurisdiction),
 * effective-dated compensation history, recurring components, tax residency,
 * country statutory profile and bank accounts. Effective-dated records are
 * appended: a new record closes the open one the day before it starts.
 */
class EmployeePayrollService
{
    public function __construct(
        protected PayrollScope $scope,
        protected PayrollAuditLogger $audit,
        protected LocalizationRegistry $localizations,
        protected CurrencyPrecision $precision,
        protected PayrollSetupService $setup,
    ) {}

    /** Employees with their current assignment, limited to the user's entities (unassigned employees only for scope.all). */
    public function getPaginatedEmployees(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $entityIds = $this->scope->entityIds($user);
        $today = now()->toDateString();

        return Employee::query()
            ->select(['id', 'employee_number', 'emp_first_name', 'emp_last_name', 'status', 'department_id'])
            ->with(['department:id,name', 'payrollAssignments' => fn ($q) => $q->overlapping($today, $today)->with(['legalEntity:id,code,name', 'payGroup:id,code,name'])])
            ->when($entityIds !== null, fn ($q) => $q->whereHas('payrollAssignments', fn ($a) => $a->whereIn('legal_entity_id', $entityIds)))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('employee_number', 'like', "%{$search}%")
                ->orWhere('emp_last_name', 'like', "%{$search}%")
                ->orWhere('emp_first_name', 'like', "%{$search}%")))
            ->when($filters['pay_group_id'] ?? null, fn ($q, $id) => $q->whereHas('payrollAssignments', fn ($a) => $a->where('pay_group_id', $id)))
            ->when(($filters['unassigned'] ?? null) && $entityIds === null, fn ($q) => $q->whereDoesntHave('payrollAssignments'))
            ->orderBy('emp_last_name')
            ->orderBy('emp_first_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function assertCanManage(User $user, Employee $employee): void
    {
        $entityIds = $this->scope->entityIds($user);
        if ($entityIds !== null && ! EmploymentAssignment::where('employee_id', $employee->id)->whereIn('legal_entity_id', $entityIds)->exists()) {
            abort(403);
        }
    }

    /**
     * The jurisdiction is the pay group's payroll country. When it differs from
     * the work country (e.g. a secondment), a reason is required.
     */
    public function createAssignment(Employee $employee, array $data, User $user): EmploymentAssignment
    {
        return DB::transaction(function () use ($employee, $data, $user) {
            $payGroup = PayGroup::findOrFail($data['pay_group_id']);
            if (! $this->scope->allows($user, $payGroup->legal_entity_id)) {
                throw ValidationException::withMessages(['pay_group_id' => 'You do not administer payroll for this legal entity.']);
            }
            if ($payGroup->payroll_country_code !== $data['work_country_code'] && empty($data['jurisdiction_override_reason'])) {
                throw ValidationException::withMessages(['jurisdiction_override_reason' => 'The work country differs from the pay group\'s payroll country; record why this payroll jurisdiction applies.']);
            }

            Employee::whereKey($employee->id)->lockForUpdate()->first();
            $overlap = EmploymentAssignment::where('employee_id', $employee->id)
                ->where('legal_entity_id', $payGroup->legal_entity_id)
                ->overlapping($data['effective_from'], $data['effective_to'] ?? '9999-12-31')
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'This employee already has an assignment with this legal entity in that date range. End it first.']);
            }

            $assignment = EmploymentAssignment::create([
                ...$data,
                'employee_id' => $employee->id,
                'legal_entity_id' => $payGroup->legal_entity_id,
                'payroll_country_code' => $payGroup->payroll_country_code,
                'created_by' => $user->id,
            ]);
            $this->audit->record($assignment, 'created', $user);

            return $assignment;
        });
    }

    public function endAssignment(EmploymentAssignment $assignment, string $effectiveTo, User $user): EmploymentAssignment
    {
        if ($effectiveTo < $assignment->effective_from->toDateString()) {
            throw ValidationException::withMessages(['effective_to' => 'The end date can\'t be before the start date.']);
        }

        $assignment->effective_to = $effectiveTo;
        $changes = $this->audit->pendingChanges($assignment);
        $assignment->save();
        $this->audit->record($assignment, 'ended', $user, $changes);

        return $assignment;
    }

    /**
     * A new salary record (pending approval). It closes the assignment's open
     * record the day before it starts; it can't start before a later record.
     */
    public function recordCompensation(EmploymentAssignment $assignment, array $data, User $user): EmployeeCompensation
    {
        return DB::transaction(function () use ($assignment, $data, $user) {
            EmploymentAssignment::whereKey($assignment->id)->lockForUpdate()->first();
            $this->setup->assertPrecision((string) $data['amount'], $this->precision->minorUnits($data['currency']), 'amount');

            $from = $data['effective_from'];
            if ($from < $assignment->effective_from->toDateString()) {
                throw ValidationException::withMessages(['effective_from' => 'Compensation can\'t start before the assignment.']);
            }
            if (EmployeeCompensation::where('employment_assignment_id', $assignment->id)->where('effective_from', '>=', $from)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'A compensation record already starts on or after this date.']);
            }
            if (EmployeeCompensation::where('employment_assignment_id', $assignment->id)->where('status', EmployeeCompensation::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Approve or discard the pending compensation change first.']);
            }

            $open = EmployeeCompensation::where('employment_assignment_id', $assignment->id)
                ->where('effective_from', '<', $from)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->first();
            if ($open) {
                $open->effective_to = Carbon::parse($from)->subDay()->toDateString();
                $changes = $this->audit->pendingChanges($open);
                $open->save();
                $this->audit->record($open, 'closed', $user, $changes);
            }

            $compensation = EmployeeCompensation::create([
                ...$data,
                'employee_id' => $assignment->employee_id,
                'employment_assignment_id' => $assignment->id,
                'status' => EmployeeCompensation::STATUS_PENDING,
                'created_by' => $user->id,
            ]);
            $this->audit->record($compensation, 'created', $user);

            return $compensation;
        });
    }

    public function approveCompensation(EmployeeCompensation $compensation, User $user): EmployeeCompensation
    {
        return DB::transaction(function () use ($compensation, $user) {
            $compensation = EmployeeCompensation::whereKey($compensation->id)->lockForUpdate()->firstOrFail();
            if ($compensation->status !== EmployeeCompensation::STATUS_PENDING) {
                throw ValidationException::withMessages(['compensation' => 'Only pending compensation can be approved.']);
            }
            if ((int) $compensation->created_by === $user->id) {
                throw ValidationException::withMessages(['compensation' => 'You cannot approve a compensation change you recorded.']);
            }

            $compensation->update(['status' => EmployeeCompensation::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->audit->record($compensation, 'approved', $user, ['status' => 'approved']);

            return $compensation;
        });
    }

    /** Discard a pending change and reopen the record it had closed. */
    public function discardCompensation(EmployeeCompensation $compensation, User $user): void
    {
        DB::transaction(function () use ($compensation, $user) {
            $compensation = EmployeeCompensation::whereKey($compensation->id)->lockForUpdate()->firstOrFail();
            if ($compensation->status !== EmployeeCompensation::STATUS_PENDING) {
                throw ValidationException::withMessages(['compensation' => 'Only pending compensation can be discarded.']);
            }

            $closed = EmployeeCompensation::where('employment_assignment_id', $compensation->employment_assignment_id)
                ->where('effective_to', Carbon::parse($compensation->effective_from)->subDay()->toDateString())
                ->first();
            $this->audit->record($compensation, 'discarded', $user, $compensation->only(['basis', 'amount', 'currency', 'effective_from']));
            $compensation->delete();
            $closed?->update(['effective_to' => null]);
        });
    }

    public function addPayComponent(EmploymentAssignment $assignment, array $data, User $user): EmployeePayComponent
    {
        $this->setup->assertPrecision((string) $data['amount'], $this->precision->minorUnits($data['currency']), 'amount');
        $component = EmployeePayComponent::create([...$data, 'employee_id' => $assignment->employee_id, 'employment_assignment_id' => $assignment->id, 'created_by' => $user->id]);
        $this->audit->record($component, 'created', $user);

        return $component;
    }

    public function endPayComponent(EmployeePayComponent $component, string $effectiveTo, User $user): void
    {
        $component->effective_to = $effectiveTo;
        $changes = $this->audit->pendingChanges($component);
        $component->save();
        $this->audit->record($component, 'ended', $user, $changes);
    }

    public function recordResidency(Employee $employee, array $data, User $user): EmployeeTaxResidency
    {
        return $this->appendEffectiveDated(EmployeeTaxResidency::class, $employee, $data, $user, []);
    }

    /** Attributes are validated against the fields the country's localization declares. */
    public function recordStatutoryProfile(Employee $employee, array $data, User $user): EmployeeStatutoryProfile
    {
        $localization = $this->localizations->for($data['country_code'])
            ?? throw ValidationException::withMessages(['country_code' => 'No payroll localization exists for this country.']);
        $declared = $localization->statutoryProfileFields();
        $fields = Validator::make($data['fields'] ?? [], collect($declared)->map(fn ($f) => $f['rules'])->all())->validate();

        return $this->appendEffectiveDated(EmployeeStatutoryProfile::class, $employee, [...$data, 'fields' => $fields], $user, ['country_code' => $data['country_code']]);
    }

    public function addBankAccount(Employee $employee, array $data, User $user): EmployeeBankAccount
    {
        return DB::transaction(function () use ($employee, $data, $user) {
            if (! empty($data['is_primary'])) {
                EmployeeBankAccount::where('employee_id', $employee->id)->where('currency', $data['currency'])->update(['is_primary' => false]);
            }
            $account = EmployeeBankAccount::create([
                ...$data,
                'employee_id' => $employee->id,
                'account_last4' => substr(preg_replace('/\s+/', '', $data['account_number']), -4),
                'created_by' => $user->id,
            ]);
            $this->audit->record($account, 'created', $user);

            return $account;
        });
    }

    public function deactivateBankAccount(EmployeeBankAccount $account, User $user): void
    {
        $account->update(['is_active' => false, 'is_primary' => false]);
        $this->audit->record($account, 'deactivated', $user, ['is_active' => false]);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function appendEffectiveDated(string $class, Employee $employee, array $data, User $user, array $partition)
    {
        return DB::transaction(function () use ($class, $employee, $data, $user, $partition) {
            Employee::whereKey($employee->id)->lockForUpdate()->first();
            $from = $data['effective_from'];

            if ($class::where('employee_id', $employee->id)->where($partition)->where('effective_from', '>=', $from)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'A record already starts on or after this date.']);
            }

            $class::where('employee_id', $employee->id)->where($partition)
                ->where('effective_from', '<', $from)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->get()
                ->each(fn ($open) => $open->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString()]));

            $record = $class::create([...$data, 'employee_id' => $employee->id, 'created_by' => $user->id]);
            $this->audit->record($record, 'created', $user);

            return $record;
        });
    }
}
