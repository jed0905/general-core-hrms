<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatingUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'prefix_id',
        'name',
        'shortcut',
        'barangay',
        'city_municipality',
        'province',
        'zip',
    ];

    public function department()
    {
        return $this->hasMany(Department::class);
    }

    public function designation()
    {
        return $this->hasMany(Designation::class);
    }
}
