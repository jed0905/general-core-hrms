<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSubordinate extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervisor_id',
        'subordinate_id',
    ];

    // Relationship to Supervisor (an Employee)
    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    // Relationship to Subordinate (also an Employee)
    public function subordinate()
    {
        return $this->belongsTo(Employee::class, 'subordinate_id');
    }
}
