<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nationality extends Model
{
    protected $fillable = [
        'name'
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'emp_nationality_id', 'id');
    }
}
