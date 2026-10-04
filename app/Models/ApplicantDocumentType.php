<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantDocumentType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'employee_document_type_id',
        'is_active',
        'required_online',
    ];

    protected function casts(): array
    {
        return [
            'employee_document_type_id' => 'integer',
            'is_active' => 'boolean',
            'required_online' => 'boolean',
        ];
    }

    /** The Employee Documents type a file of this type becomes when the applicant is hired. */
    public function employeeDocumentType(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class);
    }
}
