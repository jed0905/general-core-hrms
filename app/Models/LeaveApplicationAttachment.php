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
        'uploaded_at',
    ];

    /**
     * Private disk; files are only served through the authorized download route.
     */
    public const DISK = 'local';

    /**
     * Where attachments were stored before they moved to private storage.
     */
    public const LEGACY_DISK = 'public';

    protected function casts(): array
    {
        return [
            'leave_application_id' => 'integer',
            'uploaded_by' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function leave()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }
}
