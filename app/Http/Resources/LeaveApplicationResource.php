<?php

namespace App\Http\Resources;

use App\Helpers\AuthorizationHelper;
use App\Helpers\SupervisorHelper;
use App\Models\EmployeeLeaveCreditsHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveApplicationResource extends JsonResource
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
            'to' => Carbon::parse($this->to)->format('M j, Y'),
            'from' => Carbon::parse($this->from)->format('M j, Y'),

            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'leave_id' => $this->leave_id,
            'leave' => new LeaveResource($this->whenLoaded('leave')),
            'special_leave' => new SpecialLeaveCreditResource($this->whenLoaded('specialLeaveCredit')),

            'leave_credit' => EmployeeLeaveCreditsHistory::where('employee_id', $this->employee_id)
                ->where('leave_id', $this->leave_id)
                ->latest()
                ->first(),

            'total_earned' => $this->total_earned,
            'credit_addition' => $this->credit_addition,
            'credit_deduction' => $this->credit_deduction,
            'balance' => $this->balance,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            'status' => $this->status,
            'document_type_number' => $this->document_type_number,
            'expiration_date_from' => $this->expiration_date_from?->toDateTimeString(),
            'expiration_date_to' => $this->expiration_date_to?->toDateTimeString(),

            // 🔥 PERMISSION DRIVEN (Policy-based)
            'permissions' => [
                'canRecommend'  => Gate::allows('recommend', $this->resource),
                'canDisapprove' => Gate::allows('disapprove', $this->resource),
                'canCertify'    => Gate::allows('certify', $this->resource),
                'canApprove'    => Gate::allows('approve', $this->resource),
                'canReject'     => Gate::allows('reject', $this->resource),
            ],

            'view_link' => $this->view_link,
        ];
    }
}
