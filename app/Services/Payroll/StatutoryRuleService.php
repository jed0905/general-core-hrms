<?php

namespace App\Services\Payroll;

use App\Models\StatutoryRuleBracket;
use App\Models\StatutoryRuleVersion;
use App\Models\User;
use App\Services\Payroll\Localization\LocalizationRegistry;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Statutory rule versions for any country: draft -> verified (by another user)
 * -> superseded, or draft -> rejected. Rule keys must belong to the country's
 * localization catalog. Verifying a version closes the open verified version of
 * the same key/variant/jurisdiction the day before it starts (that one becomes
 * superseded but keeps governing its own dates). Nothing here invents values.
 */
class StatutoryRuleService
{
    public function __construct(
        protected LocalizationRegistry $localizations,
        protected PayrollAuditLogger $audit,
    ) {}

    public function getPaginatedRules(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return StatutoryRuleVersion::query()
            ->with(['creator:id,username', 'verifier:id,username'])
            ->when($filters['country_code'] ?? null, fn ($q, $c) => $q->where('country_code', $c))
            ->when($filters['rule_key'] ?? null, fn ($q, $k) => $q->where('rule_key', $k))
            ->when($filters['verification_status'] ?? null, fn ($q, $s) => $q->where('verification_status', $s))
            ->orderBy('country_code')
            ->orderBy('rule_key')
            ->orderBy('variant')
            ->orderByDesc('effective_from')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Rule keys per registered localization, for the rule form. */
    public function catalog(): array
    {
        return collect($this->localizations->all())
            ->map(fn ($l, $code) => ['code' => $code, 'name' => $l->name(), 'rules' => $l->ruleCatalog()])
            ->values()
            ->all();
    }

    public function createDraft(array $data, User $user): StatutoryRuleVersion
    {
        $this->assertInCatalog($data);

        return DB::transaction(function () use ($data, $user) {
            $rule = StatutoryRuleVersion::create([
                ...$this->attributes($data),
                'verification_status' => StatutoryRuleVersion::STATUS_DRAFT,
                'version' => $this->nextVersion($data),
                'revision' => 1,
                'created_by' => $user->id,
            ]);
            $this->syncValues($rule, $data);
            $this->audit->record($rule, 'created', $user, ['values' => $this->valuesOf($rule)]);

            return $rule;
        });
    }

    /** Editing a draft is a new revision of the same version. */
    public function updateDraft(StatutoryRuleVersion $rule, array $data, User $user): StatutoryRuleVersion
    {
        $this->assertInCatalog($data);

        return DB::transaction(function () use ($rule, $data, $user) {
            $rule = StatutoryRuleVersion::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if (! $rule->isDraft()) {
                throw ValidationException::withMessages(['rule' => 'Only draft versions can be edited; create a new version instead.']);
            }

            $before = $this->valuesOf($rule);
            $rule->fill([...$this->attributes($data), 'revision' => $rule->revision + 1]);
            $changes = $this->audit->pendingChanges($rule);
            $rule->save();
            $this->syncValues($rule, $data);
            $this->audit->record($rule, 'revised', $user, [...$changes, 'values' => ['from' => $before, 'to' => $this->valuesOf($rule->fresh())]]);

            return $rule;
        });
    }

    public function verify(StatutoryRuleVersion $rule, User $user, ?string $notes): StatutoryRuleVersion
    {
        return DB::transaction(function () use ($rule, $user, $notes) {
            $siblings = StatutoryRuleVersion::where('country_code', $rule->country_code)
                ->where('rule_key', $rule->rule_key)
                ->lockForUpdate()
                ->get();
            $rule = $siblings->firstWhere('id', $rule->id);

            if (! $rule->isDraft()) {
                throw ValidationException::withMessages(['rule' => 'Only draft versions can be verified.']);
            }
            if ((int) $rule->created_by === $user->id) {
                throw ValidationException::withMessages(['rule' => 'A rule version must be verified by someone other than the person who entered it.']);
            }

            $from = $rule->effective_from->toDateString();
            $to = $rule->effective_to?->toDateString() ?? '9999-12-31';
            $verified = $siblings->filter(fn (StatutoryRuleVersion $other) => $other->id !== $rule->id
                && $other->isVerified()
                && $other->variant === $rule->variant
                && $other->jurisdiction_code === $rule->jurisdiction_code);

            foreach ($verified as $other) {
                $otherFrom = $other->effective_from->toDateString();
                $otherTo = $other->effective_to?->toDateString() ?? '9999-12-31';
                if ($otherFrom > $to || $otherTo < $from) {
                    continue;
                }

                // An open-ended earlier version is closed and superseded; any other overlap is an error.
                if ($other->effective_to === null && $otherFrom < $from) {
                    $other->forceFill(['effective_to' => Carbon::parse($from)->subDay()->toDateString(), 'verification_status' => StatutoryRuleVersion::STATUS_SUPERSEDED])->save();
                    $this->audit->record($other, 'superseded', $user, ['by' => $rule->id, 'effective_to' => $other->effective_to->toDateString()]);

                    continue;
                }

                throw ValidationException::withMessages(['rule' => "Its dates overlap verified version #{$other->id} ({$otherFrom} to {$otherTo})."]);
            }

            $rule->forceFill([
                'verification_status' => StatutoryRuleVersion::STATUS_VERIFIED,
                'verified_by' => $user->id,
                'verified_at' => now(),
                'verification_notes' => $notes,
            ])->save();
            $this->audit->record($rule, 'verified', $user, ['notes' => $notes]);

            return $rule;
        });
    }

    public function reject(StatutoryRuleVersion $rule, User $user, string $notes): StatutoryRuleVersion
    {
        return DB::transaction(function () use ($rule, $user, $notes) {
            $rule = StatutoryRuleVersion::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if (! $rule->isDraft()) {
                throw ValidationException::withMessages(['rule' => 'Only draft versions can be rejected.']);
            }

            $rule->update(['verification_status' => StatutoryRuleVersion::STATUS_REJECTED, 'verification_notes' => $notes]);
            $this->audit->record($rule, 'rejected', $user, ['notes' => $notes]);

            return $rule;
        });
    }

    protected function assertInCatalog(array $data): void
    {
        $localization = $this->localizations->for($data['country_code'] ?? '');
        $catalog = $localization?->ruleCatalog() ?? [];

        if (! isset($catalog[$data['rule_key'] ?? ''])) {
            throw ValidationException::withMessages(['rule_key' => 'This rule key is not used by the country\'s payroll localization.']);
        }

        $entry = $catalog[$data['rule_key']];
        if (! in_array($data['calculation_method'], $entry['methods'], true)) {
            throw ValidationException::withMessages(['calculation_method' => 'Use one of: '.implode(', ', $entry['methods']).'.']);
        }
        if (isset($entry['variants']) && ! in_array($data['variant'] ?? null, $entry['variants'], true)) {
            throw ValidationException::withMessages(['variant' => 'Choose one of: '.implode(', ', $entry['variants']).'.']);
        }
    }

    protected function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'country_code', 'jurisdiction_code', 'rule_key', 'variant', 'category', 'name', 'calculation_method', 'applies_by',
            'rounding_mode', 'effective_from', 'effective_to', 'official_source', 'source_url', 'issuance_reference',
            'retrieved_at', 'notes',
        ]));
    }

    protected function nextVersion(array $data): int
    {
        return (int) StatutoryRuleVersion::where('country_code', $data['country_code'])
            ->where('rule_key', $data['rule_key'])
            ->where('variant', $data['variant'] ?? null)
            ->max('version') + 1;
    }

    protected function syncValues(StatutoryRuleVersion $rule, array $data): void
    {
        $rule->parameters()->delete();
        $rule->brackets()->delete();

        foreach ($data['parameters'] ?? [] as $parameter) {
            $rule->parameters()->create([
                'key' => $parameter['key'],
                'value' => $parameter['value'] ?? null,
                'text_value' => $parameter['text_value'] ?? null,
            ]);
        }

        foreach (array_values($data['brackets'] ?? []) as $index => $bracket) {
            $rule->brackets()->create(['sequence' => $index + 1, ...array_intersect_key($bracket, array_flip(StatutoryRuleBracket::VALUE_COLUMNS))]);
        }
    }

    protected function valuesOf(StatutoryRuleVersion $rule): array
    {
        $rule->load(['parameters', 'brackets']);

        return [
            'parameters' => $rule->parameters->map->only(['key', 'value', 'text_value'])->all(),
            'brackets' => $rule->brackets->map->only(['sequence', ...StatutoryRuleBracket::VALUE_COLUMNS])->all(),
        ];
    }
}
