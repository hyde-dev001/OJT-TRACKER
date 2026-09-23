<?php

namespace Database\Factories;

use App\Models\Internship;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Requirement> */
class RequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(10),
            'due_date' => fake()->dateTimeBetween('today', '+30 days')->format('Y-m-d'),
            'is_required' => true,
            'status' => 'incomplete',
            'student_notes' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
