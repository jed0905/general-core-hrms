<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AssignmentRequest;
use App\Http\Requests\Payroll\BankAccountRequest;
use App\Http\Requests\Payroll\CompensationRequest;
use App\Http\Requests\Payroll\EmployeePayComponentRequest;
use App\Http\Requests\Payroll\EndDateRequest;
use App\Http\Requests\Payroll\StatutoryProfileRequest;
use App\Http\Requests\Payroll\TaxResidencyRequest;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeStatutoryProfile;
use App\Models\EmployeeTaxResidency;
use App\Models\EmploymentAssignment;
use App\Models\Location;
use App\Models\PayComponent;
use App\Models\PayGroup;
use App\Services\Payroll\EmployeePayrollService;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\PayrollScope;
use App\Services\Payroll\PayrollSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An employee's payroll profile. Salary data is shown only with
 * compensation.view; statutory IDs and bank numbers are never sent in full.
 */
class PayrollEmployeeController extends Controller
{
    public function __construct(
        protected EmployeePayrollService $employees,
        protected PayrollScope $scope,
        protected LocalizationRegistry $localizations,
        protected PayrollSetupService $setup,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'pay_group_id', 'unassigned']);

        return Inertia::render('app/Payroll/Employees/Index', [
            'employees' => $this->employees->getPaginatedEmployees($request->user(), $filters),
            'filters' => $filters,
            'payGroups' => $this->scope->apply(PayGroup::query(), $request->user())->orderBy('name')->get(['id', 'code', 'name']),
            'canSeeUnassigned' => $request->user()->can('payroll.scope.all'),
        ]);
    }

    public function show(Request $request, Employee $employee): Response
    {
        $user = $request->user();
        $this->assertAccess($user, $employee);
        $canSeePay = $user->can('compensation.view');
        $entityIds = $this->scope->entityIds($user);

        $assignments = EmploymentAssignment::with(['legalEntity:id,code,name,country_code', 'payGroup:id,code,name,pay_currency,pay_frequency', 'location:id,city,address'])
            ->where('employee_id', $employee->id)
            ->when($entityIds !== null, fn ($q) => $q->whereIn('legal_entity_id', $entityIds))
            ->orderByDesc('effective_from')
            ->get();

        $profiles = EmployeeStatutoryProfile::where('employee_id', $employee->id)->orderByDesc('effective_from')->get()
            ->map(fn (EmployeeStatutoryProfile $p) => [
                'id' => $p->id,
                'country_code' => $p->country_code,
                'effective_from' => $p->effective_from->toDateString(),
                'effective_to' => $p->effective_to?->toDateString(),
                'fields' => $this->maskProfile($p),
            ]);

        return Inertia::render('app/Payroll/Employees/Show', [
            'employee' => $employee->only(['id', 'employee_number', 'emp_first_name', 'emp_last_name', 'status']),
            'assignments' => $assignments,
            'compensations' => $canSeePay
                ? EmployeeCompensation::with(['creator:id,username', 'approver:id,username', 'payGrade:id,name'])->whereIn('employment_assignment_id', $assignments->pluck('id'))->orderByDesc('effective_from')->get()
                    ->map(fn (EmployeeCompensation $c) => [...$c->toArray(), 'can' => ['approve' => $user->can('approve', $c), 'discard' => $user->can('delete', $c)]])
                : null,
            'payComponents' => $canSeePay
                ? EmployeePayComponent::with('payComponent:id,code,name,line_class')->whereIn('employment_assignment_id', $assignments->pluck('id'))->orderByDesc('effective_from')->get()
                : null,
            'residencies' => EmployeeTaxResidency::where('employee_id', $employee->id)->orderByDesc('effective_from')->get(),
            'statutoryProfiles' => $profiles,
            'bankAccounts' => EmployeeBankAccount::where('employee_id', $employee->id)->orderByDesc('is_active')->orderByDesc('is_primary')->get(),
            'options' => [
                'payGroups' => $this->scope->apply(PayGroup::query()->where('is_active', true), $user)->with('legalEntity:id,code,name')->orderBy('name')->get(['id', 'code', 'name', 'legal_entity_id', 'payroll_country_code', 'pay_currency']),
                'locations' => Location::orderBy('city')->get(['id', 'city', 'address']),
                'components' => PayComponent::where('is_system', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'line_class']),
                'currencies' => $this->setup->options()['currencies'],
                'bases' => EmployeeCompensation::BASES,
                'payGrades' => $this->setup->options()['payGrades'],
                'profileFields' => collect($this->localizations->all())->map(fn ($l) => $l->statutoryProfileFields()),
                'residencyStatuses' => EmployeeTaxResidency::STATUSES,
            ],
            'can' => [
                'manage' => $user->can('payroll.config.manage'),
                'viewPay' => $canSeePay,
                'recordPay' => $user->can('compensation.create'),
                'endComponents' => $user->can('compensation.update'),
            ],
        ]);
    }

    public function storeAssignment(AssignmentRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->createAssignment($employee, $request->validated(), $request->user());

        return back()->with('success', 'Assignment created.');
    }

    public function endAssignment(EndDateRequest $request, EmploymentAssignment $employmentAssignment): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.config.manage') && $this->scope->allows($request->user(), $employmentAssignment->legal_entity_id), 403);
        $this->employees->endAssignment($employmentAssignment, $request->validated('effective_to'), $request->user());

        return back()->with('success', 'Assignment ended.');
    }

    public function storeCompensation(CompensationRequest $request, EmploymentAssignment $employmentAssignment): RedirectResponse
    {
        abort_unless($this->scope->allows($request->user(), $employmentAssignment->legal_entity_id), 403);
        $this->employees->recordCompensation($employmentAssignment, $request->validated(), $request->user());

        return back()->with('success', 'Compensation change recorded for approval.');
    }

    public function approveCompensation(Request $request, EmployeeCompensation $employeeCompensation): RedirectResponse
    {
        $this->authorize('approve', $employeeCompensation);
        $this->employees->approveCompensation($employeeCompensation, $request->user());

        return back()->with('success', 'Compensation approved.');
    }

    public function discardCompensation(Request $request, EmployeeCompensation $employeeCompensation): RedirectResponse
    {
        $this->authorize('delete', $employeeCompensation);
        $this->employees->discardCompensation($employeeCompensation, $request->user());

        return back()->with('success', 'Pending compensation discarded.');
    }

    public function storePayComponent(EmployeePayComponentRequest $request, EmploymentAssignment $employmentAssignment): RedirectResponse
    {
        abort_unless($this->scope->allows($request->user(), $employmentAssignment->legal_entity_id), 403);
        $this->employees->addPayComponent($employmentAssignment, $request->validated(), $request->user());

        return back()->with('success', 'Recurring component added.');
    }

    public function endPayComponent(EndDateRequest $request, EmployeePayComponent $employeePayComponent): RedirectResponse
    {
        abort_unless($request->user()->can('compensation.update') && $this->scope->allows($request->user(), $employeePayComponent->assignment->legal_entity_id), 403);
        $this->employees->endPayComponent($employeePayComponent, $request->validated('effective_to'), $request->user());

        return back()->with('success', 'Recurring component ended.');
    }

    public function storeResidency(TaxResidencyRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertAccess($request->user(), $employee);
        $this->employees->recordResidency($employee, $request->validated(), $request->user());

        return back()->with('success', 'Tax residency recorded.');
    }

    public function storeStatutoryProfile(StatutoryProfileRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertAccess($request->user(), $employee);
        $this->employees->recordStatutoryProfile($employee, $request->validated(), $request->user());

        return back()->with('success', 'Statutory profile recorded.');
    }

    public function storeBankAccount(BankAccountRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertAccess($request->user(), $employee);
        $this->employees->addBankAccount($employee, $request->validated(), $request->user());

        return back()->with('success', 'Bank account added.');
    }

    public function deactivateBankAccount(Request $request, EmployeeBankAccount $employeeBankAccount): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.config.manage'), 403);
        $this->assertAccess($request->user(), $employeeBankAccount->employee);
        $this->employees->deactivateBankAccount($employeeBankAccount, $request->user());

        return back()->with('success', 'Bank account deactivated.');
    }

    private function assertAccess($user, Employee $employee): void
    {
        abort_unless($user->can('viewPayrollEmployees'), 403);
        $this->employees->assertCanManage($user, $employee);
    }

    /** Sensitive fields are shown masked; others as stored. */
    private function maskProfile(EmployeeStatutoryProfile $profile): array
    {
        $fields = $this->localizations->for($profile->country_code)?->statutoryProfileFields() ?? [];

        return collect($profile->fields ?? [])->map(function ($value, $key) use ($fields) {
            if (($fields[$key]['sensitive'] ?? false) && is_string($value) && $value !== '') {
                return str_repeat('•', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
            }

            return $value;
        })->all();
    }
}
