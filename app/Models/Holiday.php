<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'recurring',
        'length',
        'operational_country_id',
    ];

    /**
     * The operating units that belong to the holiday.
     */
    public function operatingUnits()
    {
        return $this->belongsToMany(OperatingUnit::class, 'holiday_operating_unit')
                    ->withTimestamps();
    }

}
