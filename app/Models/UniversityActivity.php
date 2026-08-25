<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversityActivity extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'type',
        'description',
        'start_at',
        'end_at',
        'status',
        'document_control_number',
    ];

    public function operatingUnits()
    {
        return $this->belongsToMany(
            OperatingUnit::class,
            'university_activity_operating_units', // pivot table name
            'activity_id',                         // foreign key on pivot referencing this model
            'operating_unit_id'                    // foreign key on pivot referencing related model
        )->withTimestamps();
    }


}
