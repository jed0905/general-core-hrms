<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'shortcut',
        'operating_unit_id',
        'parent_id',
    ];

    public function operatingUnit(){
        return $this->belongsTo(OperatingUnit::class, 'operating_unit_id', 'id');
    }
}
