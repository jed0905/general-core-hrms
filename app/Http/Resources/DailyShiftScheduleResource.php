<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyShiftScheduleResource extends JsonResource
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
            'day_of_week' => $this->day_of_week,
            'time_in' => date('h:i A', strtotime($this->time_in)),
            'time_out' =>  date('h:i A', strtotime($this->time_out)),
            'break_start' => date('h:i A', strtotime($this->break_start)),
            'break_end' => date('h:i A', strtotime($this->break_end)),
            'remarks' => $this->remarks,
            'edit_link' => $this->signed_url,
        ];
    }
}
