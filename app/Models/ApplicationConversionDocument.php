<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Provenance: which applicant document an employee document was copied from at conversion.
 */
class ApplicationConversionDocument extends Model
{
    protected $fillable = [
        'application_conversion_id',
        'applicant_document_id',
        'employee_document_id',
    ];

    protected function casts(): array
    {
        return [
            'application_conversion_id' => 'integer',
            'applicant_document_id' => 'integer',
            'employee_document_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Conversion provenance is immutable.'));
        static::deleting(fn () => throw new LogicException('Conversion provenance is immutable.'));
    }

    public function applicantDocument(): BelongsTo
    {
        return $this->belongsTo(ApplicantDocument::class);
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class);
    }
}
