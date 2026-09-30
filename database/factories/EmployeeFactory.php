<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->numerify('EMP-#####'),
            'emp_first_name' => fake()->firstName(),
            'emp_last_name' => fake()->lastName(),
            'emp_sex' => fake()->randomElement(['male', 'female']),
            'joined_date' => now()->subYears(2)->toDateString(),
            'status' => 'active',
            'supervisor_id' => null,
        ];
    }
}
