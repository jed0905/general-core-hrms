<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApplication>
 */
class LeaveApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'reason' => fake()->sentence(),
            'total_days' => 1,
            'total_hours' => 8,
            'status' => LeaveApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'approved_at' => $status === LeaveApplication::STATUS_APPROVED ? now() : null,
            'rejected_at' => $status === LeaveApplication::STATUS_REJECTED ? now() : null,
            'cancelled_at' => $status === LeaveApplication::STATUS_CANCELLED ? now() : null,
        ]);
    }
}
