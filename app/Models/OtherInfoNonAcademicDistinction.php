<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherInfoNonAcademicDistinction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'distinction',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
