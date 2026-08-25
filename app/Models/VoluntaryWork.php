<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoluntaryWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name_of_organization',
        'from',
        'to',
        'hours',
        'position',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
