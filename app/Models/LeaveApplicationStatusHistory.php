<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplicationStatusHistory extends Model
{
    protected $fillable = [
        'leave_application_id',
        'status',
        'acted_by',
        'acted_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'leave_application_id' => 'integer',
            'acted_by' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acted_by');
    }
}
