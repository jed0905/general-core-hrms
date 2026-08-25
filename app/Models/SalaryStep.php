<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_grade_id',
        'salary_step_no',
    ];

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class, 'salary_grade_id', 'id');
    }

    public function salaryMatrix()
    {
        // Remove whereColumn — just match by step_number
        return $this->hasMany(SalaryMatrix::class, 'step_number', 'salary_step_no')
            ->with('salarySchedule');
    }


}
