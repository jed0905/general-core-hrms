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
        'is_overnight',
        'is_flexible',
        'required_hours',
        'is_active'
    ];

    public function shiftSchedule()
    {
        return $this->hasMany(ShiftSchedule::class);
    }
}
