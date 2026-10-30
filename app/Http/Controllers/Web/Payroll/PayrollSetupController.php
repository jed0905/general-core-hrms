<?php

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ExchangeRateRequest;
use App\Http\Requests\Payroll\GenerateCalendarRequest;
use App\Http\Requests\Payroll\LegalEntityRequest;
use App\Http\Requests\Payroll\PayComponentRequest;
use App\Http\Requests\Payroll\PayGroupRequest;
use App\Http\Requests\Payroll\SalaryStructureRequest;
use App\Http\Requests\Payroll\UpdatePeriodRequest;
use App\Models\LegalEntity;
use App\Models\PayComponent;
use App\Models\PayGroup;
use App\Models\PayrollPeriod;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Payroll\PayrollCalendarGenerator;
use App\Services\Payroll\PayrollScope;
use App\Services\Payroll\PayrollSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payroll configuration: legal entities, pay groups with their calendars and
 * periods, pay components, salary structures and exchange rates.
 */
class PayrollSetupController extends Controller
{
    public function __construct(
        protected PayrollSetupService $setup,
        protected PayrollCalendarGenerator $calendars,
        protected PayrollScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $payGroupId = $request->integer('pay_group_id') ?: null;

        return Inertia::render('app/Payroll/Setup/Index', [
            ...$this->setup->overview($user),
            'options' => $this->setup->options(),
            'tab' => $request->query('tab', 'entities'),
            'periods' => $payGroupId && PayGroup::whereKey($payGroupId)->exists() && $this->scope->allows($user, (int) PayGroup::whereKey($payGroupId)->value('legal_entity_id'))
                ? PayrollPeriod::where('pay_group_id', $payGroupId)->withCount(['runs' => fn ($q) => $q->where('status', '<>', 'cancelled')])->orderBy('period_start')->get()
                : [],
            'selectedPayGroupId' => $payGroupId,
            'entityUsers' => $user->can('payroll.scope.all') && $user->can('payroll.config.manage')
                ? LegalEntity::with('payrollUsers:id,username')->get(['id'])->mapWithKeys(fn ($e) => [$e->id => $e->payrollUsers->pluck('id')])
                : null,
            'payrollUsers' => $user->can('payroll.scope.all') && $user->can('payroll.config.manage')
                ? User::permission(['payroll.view', 'payroll.config.view'])->orderBy('username')->get(['id', 'username'])
                : [],
            'can' => ['manage' => $user->can('payroll.config.manage'), 'assignUsers' => $user->can('payroll.scope.all') && $user->can('payroll.config.manage')],
        ]);
    }

    public function storeLegalEntity(LegalEntityRequest $request): RedirectResponse
    {
        $this->setup->saveLegalEntity($request->validated(), $request->user());

        return back()->with('success', 'Legal entity saved.');
    }

    public function updateLegalEntity(LegalEntityRequest $request, LegalEntity $legalEntity): RedirectResponse
    {
        abort_unless($this->scope->allows($request->user(), $legalEntity->id), 403);
        $this->setup->saveLegalEntity($request->validated(), $request->user(), $legalEntity);

        return back()->with('success', 'Legal entity saved.');
    }

    public function syncEntityUsers(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.scope.all'), 403);
        $data = $request->validate(['user_ids' => ['array'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $this->setup->syncEntityUsers($legalEntity, $data['user_ids'] ?? [], $request->user());

        return back()->with('success', 'Payroll users updated.');
    }

    public function storePayGroup(PayGroupRequest $request): RedirectResponse
    {
        $this->setup->savePayGroup($request->validated(), $request->user());

        return back()->with('success', 'Pay group saved.');
    }

    public function updatePayGroup(PayGroupRequest $request, PayGroup $payGroup): RedirectResponse
    {
        abort_unless($this->scope->allows($request->user(), $payGroup->legal_entity_id), 403);
        $this->setup->savePayGroup($request->validated(), $request->user(), $payGroup);

        return back()->with('success', 'Pay group saved.');
    }

    public function generateCalendar(GenerateCalendarRequest $request, PayGroup $payGroup): RedirectResponse
    {
        abort_unless($this->scope->allows($request->user(), $payGroup->legal_entity_id), 403);
        $calendar = $this->calendars->generate($payGroup, $request->integer('calendar_year'), $request->validated(), $request->user());

        return back()->with('success', $calendar->periods->count().' periods created.');
    }

    public function updatePeriod(UpdatePeriodRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        abort_unless($this->scope->allows($request->user(), $payrollPeriod->payGroup->legal_entity_id), 403);
        $this->calendars->updatePeriod($payrollPeriod, $request->validated(), $request->user());

        return back()->with('success', 'Period updated.');
    }

    public function setPeriodStatus(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.config.manage') && $this->scope->allows($request->user(), $payrollPeriod->payGroup->legal_entity_id), 403);
        $data = $request->validate(['status' => ['required', Rule::in([PayrollPeriod::STATUS_OPEN, PayrollPeriod::STATUS_CLOSED])]]);
        $this->calendars->setStatus($payrollPeriod, $data['status'], $request->user());

        return back()->with('success', 'Period '.$data['status'].'.');
    }

    public function storePayComponent(PayComponentRequest $request): RedirectResponse
    {
        $this->setup->savePayComponent($request->validated(), $request->user());

        return back()->with('success', 'Pay component saved.');
    }

    public function updatePayComponent(PayComponentRequest $request, PayComponent $payComponent): RedirectResponse
    {
        $this->setup->savePayComponent($request->validated(), $request->user(), $payComponent);

        return back()->with('success', 'Pay component saved.');
    }

    public function storeSalaryStructure(SalaryStructureRequest $request): RedirectResponse
    {
        $this->setup->saveSalaryStructure($request->validated(), $request->user());

        return back()->with('success', 'Salary structure saved.');
    }

    public function updateSalaryStructure(SalaryStructureRequest $request, SalaryStructure $salaryStructure): RedirectResponse
    {
        $this->setup->saveSalaryStructure($request->validated(), $request->user(), $salaryStructure);

        return back()->with('success', 'Salary structure saved.');
    }

    public function storeExchangeRate(ExchangeRateRequest $request): RedirectResponse
    {
        $this->setup->saveExchangeRate($request->validated(), $request->user());

        return back()->with('success', 'Exchange rate saved.');
    }
}
