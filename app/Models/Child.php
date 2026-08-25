<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    use HasFactory;

    protected $table = 'children';

    protected $fillable = [
        'employee_id',
        'fullname',
        'date_of_birth'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
