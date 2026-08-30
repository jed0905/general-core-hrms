<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplicationComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'commenter_id',
        'comment'
    ];
}
