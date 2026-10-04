<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Employee files on the private disk. Stored names are random (never the
 * client's filename); the original name is kept only for display/download.
 */
class EmployeeDocumentService
{
    public const DIRECTORY = 'employee-documents';

    public function documentsFor(Employee $employee)
    {
        return $employee->documents()
            ->with(['type:id,name,code', 'uploader:id,username'])
            ->latest('uploaded_at')
            ->latest('id')
            ->get();
    }

    /**
     * @param  array{employee_document_type_id: int, description?: ?string, expires_on?: ?string}  $data
     */
    public function upload(Employee $employee, UploadedFile $file, array $data, ?User $actor): EmployeeDocument
    {
        $path = $file->store($this->directoryFor($employee), EmployeeDocument::DISK);

        if ($path === false) {
            throw new RuntimeException('The document could not be stored.');
        }

        return $this->createRecord($employee, $path, [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'source' => EmployeeDocument::SOURCE_UPLOAD,
        ] + $data, $actor);
    }

    /**
     * Copy a file that already lives in storage (e.g. a recruitment document)
     * onto the employee's record. The source file is left untouched.
     */
    public function copyFromStorage(
        Employee $employee,
        string $sourceDisk,
        string $sourcePath,
        string $originalName,
        array $data,
        ?User $actor,
        string $source
    ): EmployeeDocument {
        $from = Storage::disk($sourceDisk);

        if (! $from->exists($sourcePath)) {
            throw new RuntimeException("Source file [{$sourcePath}] does not exist.");
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $path = $this->directoryFor($employee).'/'.Str::random(40).($extension ? '.'.strtolower($extension) : '');
        Storage::disk(EmployeeDocument::DISK)->writeStream($path, $from->readStream($sourcePath));

        return $this->createRecord($employee, $path, [
            'original_name' => $originalName,
            'mime_type' => $from->mimeType($sourcePath) ?: null,
            'file_size' => $from->size($sourcePath),
            'source' => $source,
        ] + $data, $actor);
    }

    /**
     * Metadata only; replacing a file means uploading a new document.
     */
    public function update(EmployeeDocument $document, array $data): EmployeeDocument
    {
        $document->update(Arr::only($data, ['employee_document_type_id', 'description', 'expires_on']));

        return $document;
    }

    public function delete(EmployeeDocument $document): void
    {
        $disk = $document->disk;
        $path = $document->file_path;

        DB::transaction(fn () => $document->delete());

        // Remove the file only once the record is gone.
        Storage::disk($disk)->delete($path);
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk($document->disk)->exists($document->file_path), 404);

        return Storage::disk($document->disk)->download($document->file_path, $document->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Inline view for PDFs and images only; other types fall back to a download.
     */
    public function inline(EmployeeDocument $document): StreamedResponse
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

    protected function directoryFor(Employee $employee): string
    {
        return self::DIRECTORY.'/'.$employee->id;
    }

    /**
     * Saves the row; if that fails the stored file is removed so no orphan is left.
     */
    protected function createRecord(Employee $employee, string $path, array $attributes, ?User $actor): EmployeeDocument
    {
        try {
            return DB::transaction(fn () => $employee->documents()->create([
                'employee_document_type_id' => $attributes['employee_document_type_id'],
                'original_name' => $attributes['original_name'],
                'disk' => EmployeeDocument::DISK,
                'file_path' => $path,
                'mime_type' => $attributes['mime_type'] ?? null,
                'file_size' => (int) ($attributes['file_size'] ?? 0),
                'description' => $attributes['description'] ?? null,
                'expires_on' => $attributes['expires_on'] ?? null,
                'source' => $attributes['source'],
                'uploaded_by' => $actor?->id,
                'uploaded_at' => now(),
            ]));
        } catch (Throwable $e) {
            Storage::disk(EmployeeDocument::DISK)->delete($path);
            throw $e;
        }
    }
}
