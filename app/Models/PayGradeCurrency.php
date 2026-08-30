<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayGradeCurrency extends Model
{
    protected $fillable = [
        'pay_grade_id',
        'currency',
        'minimum_salary',
        'maximum_salary'
    ];
}
