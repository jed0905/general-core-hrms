<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLeaveBalance>
 */
class EmployeeLeaveBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'balance' => 10,
            'used' => 0,
            'pending' => 0,
            'as_of_date' => now()->toDateString(),
        ];
    }
}
