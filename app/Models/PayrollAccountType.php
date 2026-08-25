<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAccountType extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 
        'operating_unit_id',
        'status'
    ];
    
    public function operatingUnit(){
        return $this->belongsTo(OperatingUnit::class, 'operating_unit_id', 'id');
    }
}
