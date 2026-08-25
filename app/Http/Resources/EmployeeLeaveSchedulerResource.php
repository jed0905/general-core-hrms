<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveSchedulerResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'leave_id' => $this->leave_id,
            'run_day' => $this->run_day,
            'credits_to_add' => $this->credits_to_add,
            'employee' => new EmployeeResource($this->employee),
            'leave' => new LeaveResource($this->leave),
            'edit_link' => $this->edit_link,
        ];
    }
}
