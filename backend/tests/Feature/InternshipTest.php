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

    public function test_missing_required_hours_has_no_progress_percentage(): void
    {
        $student = User::factory()->create();
        Internship::factory()->create([
            'student_id' => $student->id,
            'required_minutes' => null,
        ]);

        $this->stateful()->actingAs($student)
            ->getJson('/api/student/internship')
            ->assertOk()
            ->assertJsonPath('data.required_minutes', null)
            ->assertJsonPath('data.required_hours', null)
            ->assertJsonPath('data.progress.required_minutes', null)
            ->assertJsonPath('data.progress.required_hours', null)
            ->assertJsonPath('data.progress.remaining_minutes', null)
            ->assertJsonPath('data.progress.remaining_hours', null)
            ->assertJsonPath('data.progress.percentage', null);
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
