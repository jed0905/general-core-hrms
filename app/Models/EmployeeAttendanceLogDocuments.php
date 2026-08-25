<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceLogDocuments extends Model
{
    use HasFactory;
    protected $fillable = [
        'log_event_id',
        'file_path',
        'uploaded_by',
    ];

    protected $appends = ['url', 'file_name'];

    public function getUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }
        return asset('storage/' . $this->file_path);
    }

    public function getFileNameAttribute()
    {
        if (!$this->file_path) {
            return null;
        }
        return basename($this->file_path);
    }

    public function logEvent()
    {
        return $this->belongsTo(EmployeeAttendanceLogEvents::class, 'log_event_id', 'id');
    }
}
