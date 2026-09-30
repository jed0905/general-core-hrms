<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplicationComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'commenter_id',
        'comment',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }

    public function commenter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'commenter_id');
    }
}
