<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollEmployeeDeductionResource extends JsonResource
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
            'deduction_id' => $this->deduction_id,
            'amount' => $this->amount,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,
            'deduction_period' => $this->deduction_period,
            'deduction' => new PayrollDeductionResource($this->whenLoaded('deduction')),
        ];
    }
}
