<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'required_hours',
        'is_overnight',
        'is_flexible',
        'is_active',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
        'is_flexible' => 'boolean',
        'is_active' => 'boolean',
    ];
}
