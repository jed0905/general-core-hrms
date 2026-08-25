<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveCreditsHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_id' => $this->leave_id,
            'leave_type' => new LeaveResource($this->whenLoaded('leaveType')),
            'document_type_number' => $this->document_type_number,
            'special_leave' => new SpecialLeaveResource($this->whenLoaded('specialLeave')),
            'total_earned' => $this->total_earned,
            'credit_addition' => $this->credit_addition,
            'credit_deduction' => $this->credit_deduction,
            'balance' => $this->balance,
            'credit_origin' => $this->credit_origin,
            'remarks' => $this->remarks,
            'special_leave_id' => $this->special_leave_id,
            'expiration_date_from' => Carbon::parse($this->expiration_date_from)->toDateTimeString(),
            'expiration_date_to' => Carbon::parse($this->expiration_date_to)->toDateTimeString(),
            'is_expired' => $this->getIsExpiredAttribute(),
            'created_at' => Carbon::parse($this->created_at)->toDateTimeString(),
            'updated_at' => Carbon::parse($this->updated_at)->toDateTimeString(),

            // For Leave Ledger VSL
            'particulars' => $this->particulars,
            'action_taken' => $this->actionTaken,

            // For Leave Ledger TL
            'inclusive_date' => $this->inclusive_date,
        ];
    }

    protected function getLeaveShortcut(): ?string
    {
        // Special leave
        if ($this->special_leave_id && $this->specialLeave) {
            return $this->specialLeave->shortcut;
        }

        // Regular leave
        return $this->leaveType?->shortcut;
    }
}
