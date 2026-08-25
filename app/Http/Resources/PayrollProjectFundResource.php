<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollProjectFundResource extends JsonResource
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
            'name' => $this->name,
            'allocation' => $this->allocation,
            'employee_status' => $this->employee_status,
            'employee_type' => $this->employee_type,
            'status' => $this->status,
            'job_status' => new JobStatusResource($this->whenLoaded('jobStatus')),
            'operating_unit' => new OperatingUnitsResource($this->whenLoaded('operatingUnit')),
            'signed_url' => $this->signed_url,
            'assign_link' => $this->assign_link,
        ];
    }
}
