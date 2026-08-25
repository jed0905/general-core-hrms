<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningDevelopment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'title',
        'from',
        'to',
        'training_hours',
        'type_of_ld',
        'conducted_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
