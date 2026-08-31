<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'city',
        'province',
        'zip_code',
        'address',
        'phone',
        'notes',
        'is_main'
    ];

    public function employee()
    {
        return $this->hasMany(Employee::class);
    }
}
