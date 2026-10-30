<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmploymentAssignment;
use App\Models\PayComponent;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollTimeInput;
use App\Models\User;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\Support\CurrencyPrecision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Period inputs: time quantities (until a DTR module supplies them) and one-time
 * adjustments. Both start pending and are only paid once approved. Approved
 * time inputs used by a finalized run stay as they are (the payslip snapshot
 * keeps the quantities it used).
 */
class PayrollInputService
{
    public function __construct(
        protected PayrollScope $scope,
        protected LocalizationRegistry $localizations,
        protected CurrencyPrecision $precision,
        protected PayrollSetupService $setup,
        protected PayrollAuditLogger $audit,
    ) {}

    /** category => label for a period's pay group: core categories + the localization's premium categories. */
    public function categories(PayrollPeriod $period): array
    {
        $core = collect(PayrollTimeInput::CORE_CATEGORIES)->mapWithKeys(fn ($unit, $key) => [$key => ucfirst(str_replace('_', ' ', $key))])->all();
        $premium = $this->localizations->for($period->payGroup->payroll_country_code)?->premiumCategories() ?? [];

        return $core + $premium;
    }

    public function forPeriod(PayrollPeriod $period): array
    {
        return [
            'timeInputs' => PayrollTimeInput::with('employee:id,employee_number,emp_first_name,emp_last_name')
                ->where('payroll_period_id', $period->id)->orderBy('employee_id')->orderBy('category')->get(),
            'adjustments' => PayrollAdjustment::with(['employee:id,employee_number,emp_first_name,emp_last_name', 'payComponent:id,code,name,line_class'])
                ->where('payroll_period_id', $period->id)->orderBy('employee_id')->get(),
            'employees' => $this->employees($period),
        ];
    }

    public function saveTimeInput(PayrollPeriod $period, array $data, User $user): PayrollTimeInput
    {
        $this->assertOpen($period, $user);
        $this->assertEmployeeInPeriod($period, (int) $data['employee_id']);
        if (! array_key_exists($data['category'], $this->categories($period))) {
            throw ValidationException::withMessages(['category' => 'This category is not available for the pay group\'s country.']);
        }

        return DB::transaction(function () use ($period, $data, $user) {
            $input = PayrollTimeInput::firstOrNew(['payroll_period_id' => $period->id, 'employee_id' => $data['employee_id'], 'category' => $data['category']]);
            $input->fill([
                'quantity' => $data['quantity'],
                'remarks' => $data['remarks'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'status' => PayrollTimeInput::STATUS_PENDING,
                'created_by' => $user->id,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $changes = $this->audit->pendingChanges($input);
            $input->save();
            $this->audit->record($input, 'saved', $user, $changes);

            return $input;
        });
    }

    /**
     * Import "employee_number,category,quantity" rows as pending inputs.
     *
     * @return array{saved: int, errors: array<int, string>}
     */
    public function importTimeInputs(PayrollPeriod $period, string $csv, User $user): array
    {
        $saved = 0;
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        $numbers = Employee::pluck('id', 'employee_number');

        foreach ($lines as $index => $line) {
            $row = str_getcsv($line);
            if ($index === 0 && strtolower(trim($row[0] ?? '')) === 'employee_number') {
                continue;
            }
            [$number, $category, $quantity] = array_map('trim', array_pad($row, 3, ''));

            try {
                if (! isset($numbers[$number])) {
                    throw ValidationException::withMessages(['employee' => "Unknown employee number {$number}."]);
                }
                if (! preg_match('/^\d+(\.\d{1,4})?$/', $quantity)) {
                    throw ValidationException::withMessages(['quantity' => "Invalid quantity {$quantity}."]);
                }
                $this->saveTimeInput($period, ['employee_id' => $numbers[$number], 'category' => $category, 'quantity' => $quantity, 'source' => 'import'], $user);
                $saved++;
            } catch (ValidationException $e) {
                $errors[] = 'Line '.($index + 1).': '.collect($e->errors())->flatten()->first();
            }
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    /** @param  array<int, int>  $ids */
    public function approveTimeInputs(PayrollPeriod $period, array $ids, User $user): int
    {
        $this->assertOpen($period, $user);

        return DB::transaction(function () use ($period, $ids, $user) {
            $inputs = PayrollTimeInput::where('payroll_period_id', $period->id)->whereIn('id', $ids)->where('status', PayrollTimeInput::STATUS_PENDING)->lockForUpdate()->get();
            foreach ($inputs as $input) {
                $input->update(['status' => PayrollTimeInput::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
                $this->audit->record($input, 'approved', $user, ['status' => 'approved']);
            }

            return $inputs->count();
        });
    }

    public function deleteTimeInput(PayrollTimeInput $input, User $user): void
    {
        $this->assertOpen($input->period, $user);
        $this->audit->record($input, 'deleted', $user, $input->only(['employee_id', 'category', 'quantity', 'status']));
        $input->delete();
    }

    public function saveAdjustment(PayrollPeriod $period, array $data, User $user): PayrollAdjustment
    {
        $this->assertOpen($period, $user);
        $this->assertEmployeeInPeriod($period, (int) $data['employee_id']);
        $this->setup->assertPrecision((string) $data['amount'], $this->precision->minorUnits($data['currency']), 'amount');

        $component = PayComponent::findOrFail($data['pay_component_id']);
        if ($component->is_system || ! $component->is_active) {
            throw ValidationException::withMessages(['pay_component_id' => 'Choose an active, non-system pay component.']);
        }

        $adjustment = PayrollAdjustment::create([...$data, 'payroll_period_id' => $period->id, 'status' => PayrollAdjustment::STATUS_PENDING, 'created_by' => $user->id]);
        $this->audit->record($adjustment, 'created', $user);

        return $adjustment;
    }

    public function approveAdjustment(PayrollAdjustment $adjustment, User $user): PayrollAdjustment
    {
        return DB::transaction(function () use ($adjustment, $user) {
            $adjustment = PayrollAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($adjustment->status !== PayrollAdjustment::STATUS_PENDING) {
                throw ValidationException::withMessages(['adjustment' => 'Only pending adjustments can be approved.']);
            }
            if ((int) $adjustment->created_by === $user->id) {
                throw ValidationException::withMessages(['adjustment' => 'You cannot approve an adjustment you entered.']);
            }

            $adjustment->update(['status' => PayrollAdjustment::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->audit->record($adjustment, 'approved', $user, ['status' => 'approved']);

            return $adjustment;
        });
    }

    public function deleteAdjustment(PayrollAdjustment $adjustment, User $user): void
    {
        if ($adjustment->status === PayrollAdjustment::STATUS_APPLIED) {
            throw ValidationException::withMessages(['adjustment' => 'Applied adjustments are kept.']);
        }
        $this->audit->record($adjustment, 'deleted', $user, $adjustment->only(['employee_id', 'pay_component_id', 'amount', 'currency', 'status']));
        $adjustment->delete();
    }

    public function assertOpen(PayrollPeriod $period, User $user): void
    {
        if (! $this->scope->allows($user, (int) $period->payGroup->legal_entity_id)) {
            abort(403);
        }
        if ($period->status !== PayrollPeriod::STATUS_OPEN) {
            throw ValidationException::withMessages(['period' => 'This payroll period is closed.']);
        }
    }

    protected function assertEmployeeInPeriod(PayrollPeriod $period, int $employeeId): void
    {
        if (! $this->employees($period)->contains('id', $employeeId)) {
            throw ValidationException::withMessages(['employee_id' => 'This employee has no assignment in this pay group during the period.']);
        }
    }

    protected function employees(PayrollPeriod $period): Collection
    {
        return Employee::whereIn('id', EmploymentAssignment::where('pay_group_id', $period->pay_group_id)
            ->overlapping($period->period_start->toDateString(), $period->period_end->toDateString())
            ->select('employee_id'))
            ->orderBy('emp_last_name')
            ->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name']);
    }
}
