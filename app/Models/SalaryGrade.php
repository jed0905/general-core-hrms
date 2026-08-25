<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_grade',
    ];

    public function salarySteps()
    {
        return $this->hasMany(SalaryStep::class, 'salary_grade_id', 'id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'salary_grade_id', 'id');
    }

    public function position()
    {
        return $this->hasMany(Position::class);
    }

    public function salaryMatrices()
    {
        return $this->hasMany(SalaryMatrix::class, 'salay_grade_id', 'id');
    }
}
