<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A file belonging to the applicant (stored once, on the private disk).
 * Applications link to it through application_documents instead of copying it.
 */
class ApplicantDocument extends Model
{
    public const DISK = 'local';

    public const INLINE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'applicant_id',
        'applicant_document_type_id',
        'original_name',
        'disk',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $hidden = [
        'disk',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'applicant_id' => 'integer',
            'applicant_document_type_id' => 'integer',
            'file_size' => 'integer',
            'uploaded_by' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ApplicantDocumentType::class, 'applicant_document_type_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_documents')->withTimestamps();
    }

    public function canPreviewInline(): bool
    {
        return in_array($this->mime_type, self::INLINE_MIME_TYPES, true);
    }
}
