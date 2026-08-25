<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecialLeaveCreditResource extends JsonResource
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
            'special_leave' => new SpecialLeaveResource($this->whenLoaded('specialLeave')),
            'balance' => $this->balance,
            'expiration_date' => Carbon::parse($this->expiration_date_to)->format('F j, Y')
        ];
    }
}
