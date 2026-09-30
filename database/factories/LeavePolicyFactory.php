<?php

namespace Database\Factories;

use App\Models\LeavePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Policy',
            'description' => null,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
