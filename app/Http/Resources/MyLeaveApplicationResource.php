<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyLeaveApplicationResource extends JsonResource
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
            'application_date' => Carbon::parse($this->created_at)->format('M j, Y'),
            'inclusive_dates' => $this->leaveDates->map(function ($date) {

                $formattedDuration = match ($date->duration) {
                    'full_day' => 'Full Day',
                    'half_day_am' => 'Half Day (AM)',
                    'half_day_pm' => 'Half Day (PM)',
                    default => ucwords(str_replace('_', ' ', $date->duration)),
                };

                return [
                    'date' => Carbon::parse($date->date)->format('D, M j, Y'), // 👈 added day shortcut
                    'duration' => $formattedDuration,
                    'credits' => $date->credits,
                ];
            }),
            'from' => Carbon::parse($this->from)->format('M j, Y'),
            'to' => Carbon::parse($this->to)->format('M j, Y'),
            'employee_name' => $this->employee?->personalInformation?->getFullNameAttributeDesc(),
            'leave_type' => $this->leave->name ?? null,
            'special_leave' => $this->specialLeaveCredit->specialLeave->name ?? null,
            'status' => $this->currentStatus->status ?? null,
            'view_link' => $this->view_link,
        ];
    }
}
