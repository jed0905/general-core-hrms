<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionsResource extends JsonResource
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
            'name' => $this->government_position->name,
            'shortcut' => $this->government_position->shortcut,
            'plantilla_item_number' => $this->plantilla_item_number,
            'salary_grade' => $this->salary_grade?->salary_grade,
            'edit_link' => $this->edit_link,
            'operating_unit_id' => $this->operating_unit_id,
            'operating_unit' => $this->whenLoaded('operating_unit'),
        ];
    }
}
