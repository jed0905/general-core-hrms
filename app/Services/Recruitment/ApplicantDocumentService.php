<?php

namespace App\Services\Recruitment;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Applicant files on the private disk (random stored names, served only through
 * authorized routes). Each file is stored once; applications link to it.
 */
class ApplicantDocumentService
{
    public const DIRECTORY = 'recruitment/applicants';

    public function upload(Applicant $applicant, UploadedFile $file, array $data, ?User $actor): ApplicantDocument
    {
        $path = $file->store(self::DIRECTORY.'/'.$applicant->id, ApplicantDocument::DISK);

        if ($path === false) {
            throw new RuntimeException('The document could not be stored.');
        }

        try {
            return DB::transaction(fn () => $applicant->documents()->create([
                'applicant_document_type_id' => $data['applicant_document_type_id'],
                'original_name' => $file->getClientOriginalName(),
                'disk' => ApplicantDocument::DISK,
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => (int) $file->getSize(),
                'description' => $data['description'] ?? null,
                'uploaded_by' => $actor?->id,
                'uploaded_at' => now(),
            ]));
        } catch (Throwable $e) {
            Storage::disk(ApplicantDocument::DISK)->delete($path);
            throw $e;
        }
    }

    /**
     * Documents already submitted with an application are kept as evidence.
     */
    public function delete(ApplicantDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $document = ApplicantDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($document->applications()->exists()) {
                throw ValidationException::withMessages(['document' => ['This document was submitted with an application and cannot be deleted.']]);
            }

            $document->delete();
        });

        Storage::disk($document->disk)->delete($document->file_path);
    }

    public function download(ApplicantDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk($document->disk)->exists($document->file_path), 404);

        return Storage::disk($document->disk)->download($document->file_path, $document->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function inline(ApplicantDocument $document): StreamedResponse
    {
        if (! $document->canPreviewInline()) {
            return $this->download($document);
        }

        abort_unless(Storage::disk($document->disk)->exists($document->file_path), 404);

        return Storage::disk($document->disk)->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
