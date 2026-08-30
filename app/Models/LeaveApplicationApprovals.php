<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class LeaveApplicationApprovals extends Model
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
}
