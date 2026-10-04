<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    /**
     * Private disk: files are only served through the authorized download/view routes.
     */
    public const DISK = 'local';

    public const SOURCE_UPLOAD = 'upload';

    /** Copied from an applicant document when a hired applicant was converted (see application_conversion_documents). */
    public const SOURCE_RECRUITMENT = 'recruitment';

    /**
     * MIME types safe to show inline in the browser; everything else is downloaded.
     */
    public const INLINE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'employee_id',
        'employee_document_type_id',
        'original_name',
        'disk',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'expires_on',
        'source',
        'uploaded_by',
        'uploaded_at',
    ];

    /**
     * The storage location is never sent to the browser.
     */
    protected $hidden = [
        'disk',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'employee_document_type_id' => 'integer',
            'file_size' => 'integer',
            'uploaded_by' => 'integer',
            'expires_on' => 'date:Y-m-d',
            'uploaded_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class, 'employee_document_type_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function canPreviewInline(): bool
    {
        return in_array($this->mime_type, self::INLINE_MIME_TYPES, true);
    }
}
