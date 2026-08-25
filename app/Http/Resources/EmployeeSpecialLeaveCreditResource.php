<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSpecialLeaveCreditResource extends JsonResource
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
            'leave_id' => $this->leave_id,
            'special_leave_id' => $this->special_leave_id,
            'balance' => $this->balance,
            'expiration_date_to' => $this->expiration_date_to,
            'is_expired' => $this->is_expired ?? false,
            'special_leave' => $this->whenLoaded('specialLeave'),
        ];
    }
}
