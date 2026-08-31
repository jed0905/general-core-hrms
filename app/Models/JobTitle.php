<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    protected $fillable = [
        'job_title',
        'job_description'
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
