<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplicationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function leave()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }
}
