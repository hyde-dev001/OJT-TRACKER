<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Internship> */
class InternshipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'required_minutes' => 30000,
            'start_date' => today()->subDays(30),
            'end_date' => today()->addMonths(3),
            'status' => 'active',
        ];
    }
}
