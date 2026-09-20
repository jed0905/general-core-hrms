<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class LeaveApplicationApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'approver_id',
        'approval_order',
        'status',
        'acted_at',
        'remarks'
    ];

    public function application()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }
}
