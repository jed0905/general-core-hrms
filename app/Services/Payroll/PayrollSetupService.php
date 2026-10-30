<?php

namespace App\Services\Payroll;

use App\Models\Currency;
use App\Models\EmployeeCompensation;
use App\Models\ExchangeRate;
use App\Models\LegalEntity;
use App\Models\PayComponent;
use App\Models\PayComponentTreatment;
use App\Models\PayGrade;
use App\Models\PayGroup;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Payroll\Localization\LocalizationRegistry;
use App\Services\Payroll\Support\CurrencyPrecision;
use App\Services\Payroll\Support\Decimal;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll configuration: legal entities, pay groups, pay components (and their
 * per-country treatments), salary structures and exchange rates. Every write is
 * audited. Records referenced by payroll history are deactivated, not deleted.
 */
class PayrollSetupService
{
    public function __construct(
        protected PayrollAuditLogger $audit,
        protected LocalizationRegistry $localizations,
        protected CurrencyPrecision $precision,
        protected PayrollScope $scope,
    ) {}

    /** Everything the setup page lists (configuration volumes are small). */
    public function overview(User $user): array
    {
        $entityIds = $this->scope->entityIds($user);
        $inScope = fn ($q, string $column = 'legal_entity_id') => $entityIds === null ? $q : $q->whereIn($column, $entityIds);

        return [
            'legalEntities' => $inScope(LegalEntity::query(), 'id')->orderBy('name')->get(),
            'payGroups' => $inScope(PayGroup::with('legalEntity:id,code,name'))->orderBy('name')->get(),
            'payComponents' => PayComponent::with('treatments')->where(fn ($q) => $q->whereNull('legal_entity_id')->when($entityIds !== null, fn ($q) => $q->orWhereIn('legal_entity_id', $entityIds), fn ($q) => $q->orWhereNotNull('legal_entity_id')))->orderBy('priority')->orderBy('code')->get(),
            'salaryStructures' => SalaryStructure::with('grades.payGrade:id,name')->where(fn ($q) => $q->whereNull('legal_entity_id')->when($entityIds !== null, fn ($q) => $q->orWhereIn('legal_entity_id', $entityIds), fn ($q) => $q->orWhereNotNull('legal_entity_id')))->orderByDesc('effective_from')->get(),
            'exchangeRates' => ExchangeRate::orderByDesc('rate_date')->orderBy('base_currency')->limit(200)->get(),
        ];
    }

    /** Option lists for the setup forms. */
    public function options(): array
    {
        return [
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(['code', 'name', 'minor_units']),
            'localizations' => collect($this->localizations->all())->map(fn ($l, $code) => [
                'code' => $code,
                'name' => $l->name(),
                'taxTreatments' => $l->taxTreatments(),
                'contributionTreatments' => $l->contributionTreatments(),
            ])->values(),
            'frequencies' => PayGroup::FREQUENCIES,
            'prorationMethods' => PayGroup::PRORATION_METHODS,
            'roundingModes' => PayGroup::ROUNDING_MODES,
            'lineClasses' => PayComponent::LINE_CLASSES,
            'categories' => PayComponent::CATEGORIES,
            'bases' => EmployeeCompensation::BASES,
            'payGrades' => PayGrade::orderBy('name')->get(['id', 'name']),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];
    }

    public function saveLegalEntity(array $data, User $user, ?LegalEntity $entity = null): LegalEntity
    {
        return $this->save($entity ?? new LegalEntity, $data, $user);
    }

    public function savePayGroup(array $data, User $user, ?PayGroup $payGroup = null): PayGroup
    {
        if (! $this->scope->allows($user, (int) $data['legal_entity_id'])) {
            throw ValidationException::withMessages(['legal_entity_id' => 'You do not administer payroll for this legal entity.']);
        }

        if ($payGroup && $payGroup->exists && $payGroup->periods()->whereHas('runs')->exists()) {
            $locked = ['legal_entity_id', 'payroll_country_code', 'pay_frequency', 'pay_currency'];
            foreach ($locked as $field) {
                if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $payGroup->{$field}) {
                    throw ValidationException::withMessages([$field => 'This pay group already has payroll runs; create a new pay group instead of changing its entity, country, frequency or currency.']);
                }
            }
        }

        return $this->save($payGroup ?? new PayGroup, $data, $user);
    }

    /**
     * @param  array{treatments?: array<int, array{country_code: string, tax_treatment: string, contribution_treatment: string}>}  $data
     */
    public function savePayComponent(array $data, User $user, ?PayComponent $component = null): PayComponent
    {
        return DB::transaction(function () use ($data, $user, $component) {
            $component ??= new PayComponent;
            if ($component->exists && $component->is_system) {
                // System components keep their code and class; only name, treatments and status change.
                $data = array_intersect_key($data, array_flip(['name', 'description', 'treatments']));
            }

            $treatments = $data['treatments'] ?? null;
            unset($data['treatments']);
            $component = $this->save($component, $data, $user);

            if ($treatments !== null) {
                foreach ($treatments as $treatment) {
                    $localization = $this->localizations->for($treatment['country_code']);
                    if (! $localization
                        || ! array_key_exists($treatment['tax_treatment'], $localization->taxTreatments())
                        || ! array_key_exists($treatment['contribution_treatment'], $localization->contributionTreatments())) {
                        throw ValidationException::withMessages(['treatments' => "Unknown treatment for {$treatment['country_code']}."]);
                    }
                }

                $component->treatments()->delete();
                foreach ($treatments as $treatment) {
                    PayComponentTreatment::create(['pay_component_id' => $component->id, ...$treatment]);
                }
                $this->audit->record($component, 'treatments_updated', $user, ['treatments' => $treatments]);
            }

            return $component->load('treatments');
        });
    }

    /**
     * @param  array{grades?: array<int, array{pay_grade_id: int, minimum_amount: string, midpoint_amount?: ?string, maximum_amount: string}>}  $data
     */
    public function saveSalaryStructure(array $data, User $user, ?SalaryStructure $structure = null): SalaryStructure
    {
        return DB::transaction(function () use ($data, $user, $structure) {
            $grades = $data['grades'] ?? [];
            unset($data['grades']);
            $places = $this->precision->minorUnits($data['currency']);

            foreach ($grades as $index => $grade) {
                foreach (['minimum_amount', 'midpoint_amount', 'maximum_amount'] as $field) {
                    if (($grade[$field] ?? null) !== null) {
                        $this->assertPrecision((string) $grade[$field], $places, "grades.{$index}.{$field}");
                    }
                }
                if (Decimal::cmp((string) $grade['maximum_amount'], (string) $grade['minimum_amount']) < 0) {
                    throw ValidationException::withMessages(["grades.{$index}.maximum_amount" => 'The maximum must not be below the minimum.']);
                }
            }

            $structure = $this->save($structure ?? new SalaryStructure, $data, $user);
            $structure->grades()->delete();
            foreach ($grades as $grade) {
                $structure->grades()->create($grade);
            }

            return $structure->load('grades');
        });
    }

    public function saveExchangeRate(array $data, User $user): ExchangeRate
    {
        if ($data['base_currency'] === $data['quote_currency']) {
            throw ValidationException::withMessages(['quote_currency' => 'Choose two different currencies.']);
        }

        return $this->save(new ExchangeRate, $data + ['created_by' => $user->id], $user);
    }

    /**
     * Which users administer this entity's payroll (for users without payroll.scope.all).
     *
     * @param  array<int, int>  $userIds
     */
    public function syncEntityUsers(LegalEntity $entity, array $userIds, User $user): void
    {
        DB::transaction(function () use ($entity, $userIds, $user) {
            $before = $entity->payrollUsers()->pluck('users.id')->all();
            $entity->payrollUsers()->sync($userIds);
            $this->audit->record($entity, 'payroll_users_updated', $user, ['from' => $before, 'to' => array_values($userIds)]);
        });
    }

    /** Amounts may not carry more decimals than the currency's ISO 4217 minor units. */
    public function assertPrecision(string $amount, int $places, string $field): void
    {
        if (Decimal::cmp(Decimal::format($amount, $places), $amount) !== 0) {
            throw ValidationException::withMessages([$field => "This currency allows at most {$places} decimal places."]);
        }
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    protected function save(Model $model, array $data, User $user): Model
    {
        return DB::transaction(function () use ($model, $data, $user) {
            $creating = ! $model->exists;
            $model->fill($data);
            $changes = $this->audit->pendingChanges($model);
            $model->save();
            $this->audit->record($model, $creating ? 'created' : 'updated', $user, $changes);

            return $model;
        });
    }
}
