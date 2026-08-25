<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherInfoSpecialSkills extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'special_skill',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
