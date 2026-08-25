<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollProjectFund extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'allocation',
        'employee_status',
        'employee_type',
        'status',
        'operating_unit_id',
    ];

    public function jobStatus(){
        return $this->belongsTo(JobStatus::class, 'employee_status', 'id');
    }

    public function operatingUnit(){
        return $this->belongsTo(OperatingUnit::class, 'operating_unit_id', 'id');
    }
}
