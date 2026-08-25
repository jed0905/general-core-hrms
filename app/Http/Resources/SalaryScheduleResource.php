<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryScheduleResource extends JsonResource
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
            'law_reference' => $this->law_reference,
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to,
            'is_active' => $this->is_active,
            'edit_link' => $this->edit_link ?? null,
            'view_link' => $this->view_link ?? null,
        ];
    }
}
