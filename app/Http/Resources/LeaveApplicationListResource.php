<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class LeaveApplicationListResource extends JsonResource
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
            'from' => Carbon::parse($this->from)->format('M j, Y'),
            'to' => Carbon::parse($this->to)->format('M j, Y'),
            'inclusive_dates' => $this->leaveDates->map(function ($date) {

                $formattedDuration = match ($date->duration) {
                    'full_day' => 'Full Day',
                    'half_day_am' => 'Half Day (AM)',
                    'half_day_pm' => 'Half Day (PM)',
                    default => ucwords(str_replace('_', ' ', $date->duration)),
                };

                return [
                    'date' => Carbon::parse($date->date)->format('D, M j, Y'), // 👈 added day shortcut
                    'date_raw' => Carbon::parse($date->date)->format('Y-m-d'),
                    'duration' => $formattedDuration,
                    'duration_raw' => $date->duration,
                    'credits' => $date->credits,
                ];
            }),
            'employee_name' => $this->employee?->personalInformation?->getFullNameAttributeDesc(),
            'leave_type' => $this->leave->name ?? null,
            'special_leave' => $this->specialLeaveCredit->specialLeave->name ?? null,
            'status' => $this->currentStatus->status ?? null,

            // 🔥 PERMISSION DRIVEN (Policy-based)
            'permissions' => [
                'canRecommend' => Gate::allows('recommend', $this->resource),
                'canCertify' => Gate::allows('certify', $this->resource),
                'canApprove' => Gate::allows('approve', $this->resource),
                'canCancel' => Gate::allows('cancel', $this->resource),
            ],

            'view_link' => $this->view_link,
        ];
    }
}
