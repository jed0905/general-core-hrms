<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;
    protected $fillable = [
        'government_positions_id',
        'plantilla_item_number',
        'salary_grade_id',
        'operating_unit_id',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'positions_id', 'id');
    }

    public function government_position()
    {
        return $this->belongsTo(GovernmentPosition::class, 'government_positions_id', 'id');
    }

    public function salary_grade()
    {
        return $this->belongsTo(SalaryGrade::class, 'salary_grade_id', 'id');
    }

    public function operating_unit()
    {
        return $this->belongsTo(OperatingUnit::class, 'operating_unit_id', 'id');
    }
}
