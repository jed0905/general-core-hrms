<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryMatrix extends Model
{
    use HasFactory;

    protected $table = 'salary_matrices';

    protected $fillable = [
        'salary_schedule_id',
        'salary_grade_id',
        'step_number',
        'amount',
    ];

    public function salarySchedule()
    {
        return $this->belongsTo(SalarySchedule::class);
    }

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }


}
