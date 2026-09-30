<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Leave',
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'description' => null,
            'is_active' => true,
        ];
    }
}
