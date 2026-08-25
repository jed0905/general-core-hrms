<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PayrollAccountTypeResource;

class PayrollEmployeeAccountInformationResource extends JsonResource
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
            'employee_number' => $this->employee_number,
            'account_type_id' => $this->account_type_id,
            'account_number' => $this->account_number,
            'status' => $this->status,
            'account_type' => new PayrollAccountTypeResource($this->whenLoaded('accountType')),
        ];
    }
}
