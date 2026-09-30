<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable movement type. Business logic keys off `code`, never `name`.
 */
class EmployeeMovementType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'affected_fields',
        'employee_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'affected_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EmployeeMovement::class, 'movement_type_id');
    }
}
