<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationScreening extends Model
{
    public const RESULT_PASSED = 'passed';

    public const RESULT_FAILED = 'failed';

    public const RESULTS = [self::RESULT_PASSED, self::RESULT_FAILED];

    protected $fillable = [
        'application_id',
        'result',
        'remarks',
        'screened_on',
        'screened_by',
        'rejection_reason_id',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'screened_on' => 'date:Y-m-d',
            'screened_by' => 'integer',
            'rejection_reason_id' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    public function passed(): bool
    {
        return $this->result === self::RESULT_PASSED;
    }
}
