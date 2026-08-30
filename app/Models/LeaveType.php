<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_paid',
        'requires_attachment',
        'allows_half_day',
        'allows_hourly',
        'allows_negative_balance'
    ];

    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
