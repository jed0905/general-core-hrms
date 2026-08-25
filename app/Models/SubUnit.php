<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'shortcut',
        'department_unit_id',
        'parent_id',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_unit_id');
    }
}
