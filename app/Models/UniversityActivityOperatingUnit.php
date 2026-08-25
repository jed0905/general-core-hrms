<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversityActivityOperatingUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'operating_unit_id',
    ];

    public function activity()
    {
        return $this->belongsTo(UniversityActivity::class, 'activity_id');
    }

    public function operatingUnit()
    {
        return $this->belongsTo(OperatingUnit::class, 'operating_unit_id');
    }
}
