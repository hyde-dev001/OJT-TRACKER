<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_configure_required_work_hours(): void
    {
        $student = User::factory()->create();
        $internship = Internship::factory()->create(['student_id' => $student->id]);

        $this->stateful()->actingAs($student)
            ->putJson('/api/student/internship', ['required_hours' => 450])
            ->assertOk()
            ->assertJsonPath('data.required_hours', 450)
            ->assertJsonPath('data.required_minutes', 27000);

        $this->assertDatabaseHas('internships', [
            'id' => $internship->id,
            'required_minutes' => 27000,
        ]);
    }

    public function test_required_work_hours_must_be_positive(): void
    {
        $student = User::factory()->create();
        Internship::factory()->create(['student_id' => $student->id]);

        $this->stateful()->actingAs($student)
            ->putJson('/api/student/internship', ['required_hours' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('required_hours');
    }
}
