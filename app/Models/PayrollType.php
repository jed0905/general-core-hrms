<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollType extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'status',
        'operating_unit_id',
    ];

    public function operatingUnit()
    {
        return $this->belongsTo(OperatingUnit::class);
    }
}
