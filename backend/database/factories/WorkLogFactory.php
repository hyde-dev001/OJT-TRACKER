<?php

namespace Database\Factories;

use App\Models\Internship;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\WorkLog> */
class WorkLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'work_date' => fake()->unique()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'time_in' => '08:00',
            'time_out' => '17:00',
            'break_minutes' => 60,
            'rendered_minutes' => 480,
            'accomplishment_summary' => 'Completed assigned OJT activities.',
            'status' => 'completed',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
