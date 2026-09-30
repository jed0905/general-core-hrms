<?php

namespace App\Services;

use App\Models\EmployeeMovementType;
use Illuminate\Support\Collection;

class EmployeeMovementTypeService
{
    public function getTypes(array $filters = []): Collection
    {
        return EmployeeMovementType::query()
            ->withCount('movements')
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null,
                fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->get();
    }

    public function createType(array $data): EmployeeMovementType
    {
        return EmployeeMovementType::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'affected_fields' => array_values($data['affected_fields'] ?? []),
            'employee_status' => $data['employee_status'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * The code never changes; past movements keep pointing at the same type.
     */
    public function updateType(EmployeeMovementType $type, array $data): EmployeeMovementType
    {
        $type->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'affected_fields' => array_values($data['affected_fields'] ?? []),
            'employee_status' => $data['employee_status'] ?? null,
            'is_active' => $data['is_active'] ?? $type->is_active,
        ]);

        return $type;
    }

    /**
     * Archive = deactivate. Types are never deleted, so history keeps its type.
     */
    public function archiveType(EmployeeMovementType $type): bool
    {
        return $type->update(['is_active' => false]);
    }
}
