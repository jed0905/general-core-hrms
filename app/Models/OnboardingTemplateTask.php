<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One task definition in an onboarding template.
 */
class OnboardingTemplateTask extends Model
{
    public const CATEGORIES = ['before_start', 'first_day', 'first_week', 'first_month', 'other'];

    public const ASSIGNEE_EMPLOYEE = 'employee';

    public const ASSIGNEE_HR = 'hr';

    public const ASSIGNEE_SUPERVISOR = 'supervisor';

    public const ASSIGNEE_SPECIFIC = 'specific_employee';

    public const ASSIGNEE_TYPES = [self::ASSIGNEE_EMPLOYEE, self::ASSIGNEE_HR, self::ASSIGNEE_SUPERVISOR, self::ASSIGNEE_SPECIFIC];

    public const RELATIVE_START = 'start_date';

    public const RELATIVE_CREATED = 'created';

    protected $fillable = [
        'onboarding_template_id',
        'title',
        'description',
        'category',
        'sort_order',
        'assignee_type',
        'assignee_employee_id',
        'due_relative_to',
        'due_offset_days',
        'is_required',
        'employee_visible',
        'requires_verification',
        'required_document_type_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_template_id' => 'integer',
            'sort_order' => 'integer',
            'assignee_employee_id' => 'integer',
            'due_offset_days' => 'integer',
            'is_required' => 'boolean',
            'employee_visible' => 'boolean',
            'requires_verification' => 'boolean',
            'required_document_type_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    public function requiredDocumentType(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class, 'required_document_type_id');
    }
}
