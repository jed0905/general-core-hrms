<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMovementType extends Model
{
    protected $fillable = [
        'code',
        'name'
    ];
}
