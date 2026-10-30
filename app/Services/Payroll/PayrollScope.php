<?php

namespace App\Services\Payroll;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Which legal entities' payroll a user may see. payroll.scope.all grants every
 * entity; anyone else sees only the entities assigned to them in
 * payroll_entity_user. Applied in policies, index queries, exports and reports.
 * Never decided by role name.
 */
class PayrollScope
{
    /** @return array<int, int>|null entity ids, or null for all entities */
    public function entityIds(User $user): ?array
    {
        if ($user->can('payroll.scope.all')) {
            return null;
        }

        return $user->payrollEntities()->pluck('legal_entities.id')->map(fn ($id) => (int) $id)->all();
    }

    public function allows(User $user, int $legalEntityId): bool
    {
        $ids = $this->entityIds($user);

        return $ids === null || in_array($legalEntityId, $ids, true);
    }

    public function apply(Builder $query, User $user, string $column = 'legal_entity_id'): Builder
    {
        $ids = $this->entityIds($user);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** Legal entities the user may pick from (id, code, name, country, currency). */
    public function entityOptions(User $user): array
    {
        return $this->apply(LegalEntity::query(), $user, 'id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'country_code', 'functional_currency'])
            ->all();
    }
}
