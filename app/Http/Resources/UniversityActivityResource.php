<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class UniversityActivityResource extends JsonResource
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
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'start_at' => Carbon::parse($this->start_at)->format('F j,Y'),
            'end_at' => Carbon::parse($this->end_at)->format('F j,Y'),
            'status' => $this->status,
            'document_control_number' => $this->document_control_number,
            'operating_units' => $this->operatingUnits->map(function($operatingUnit){
                return [
                    'id' => $operatingUnit->id,
                    'name' => $operatingUnit->name,
                    'shortcut' => $operatingUnit->shortcut,
                ];
            })->pluck('shortcut'),
            'edit_link' => URL::signedRoute('hrmanagement.universityActivities.edit', ['id' => $this->id]),
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y'),
            'updated_at' => Carbon::parse($this->updated_at)->format('F j,Y'),
        ];
    }
}
