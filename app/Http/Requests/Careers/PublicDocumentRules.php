<?php

namespace App\Http\Requests\Careers;

use Illuminate\Validation\Rule;

/**
 * Upload rules for untrusted public files: an allow-list checked against the
 * client extension AND the detected content type, and a size cap. Stored
 * privately with random names by ApplicantDocumentService.
 */
final class PublicDocumentRules
{
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    public const MIME_TYPES = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg', 'image/png',
    ];

    public const MAX_KB = 5120;

    public const MAX_FILES = 10;

    public static function file(): array
    {
        return ['required', 'file', 'extensions:'.implode(',', self::EXTENSIONS), 'mimes:'.implode(',', self::EXTENSIONS), 'mimetypes:'.implode(',', self::MIME_TYPES), 'max:'.self::MAX_KB];
    }

    public static function type(): array
    {
        return ['required', 'integer', Rule::exists('applicant_document_types', 'id')->where('is_active', true)];
    }
}
