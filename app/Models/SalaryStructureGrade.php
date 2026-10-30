<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructureGrade extends Model
{
    protected $fillable = ['salary_structure_id', 'pay_grade_id', 'minimum_amount', 'midpoint_amount', 'maximum_amount'];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:4',
            'midpoint_amount' => 'decimal:4',
            'maximum_amount' => 'decimal:4',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class);
    }
}
