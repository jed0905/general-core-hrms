<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubordinateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->id,
            'name' => $this->personalInformation?->getFullNameAttributeAsc(),
            'position_title' => $this->position->government_position->name ?? '',
            'plantilla_item_number' => $this->position->plantilla_item_number ?? 'N/A',
        ];
    }
}
