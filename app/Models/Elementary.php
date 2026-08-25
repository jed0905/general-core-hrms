<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Elementary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name_of_school',
        'degree_course',
        'period_from',
        'period_to',
        'highest_level',
        'year_graduated',
        'academic_award'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
