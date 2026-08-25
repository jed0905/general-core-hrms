<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'law_reference',
        'effective_from',
        'effective_to',
        'is_active',
    ];
}
