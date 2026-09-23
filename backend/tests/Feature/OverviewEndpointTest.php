<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_student_overview(): void
    {
        $this->getJson('/api/student/overview')->assertUnauthorized();
    }

    public function test_student_overview_is_scoped_to_the_authenticated_current_internship(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $own = Internship::factory()->create([
            'student_id' => $student->id,
            'required_minutes' => 12_000,
        ]);
        Internship::factory()->create([
            'student_id' => $other->id,
            'required_minutes' => 99_999,
        ]);

        $this->stateful()->actingAs($student)
            ->getJson('/api/student/overview?student_id='.$other->id.'&internship_id=999999')
            ->assertOk()
            ->assertJsonPath('data.progress.required_minutes', $own->required_minutes)
            ->assertJsonMissingPath('data.internship.student_id')
            ->assertJsonMissingPath('data.internship.id')
            ->assertJsonStructure([
                'data' => [
                    'internship' => ['start_date', 'end_date', 'work_days', 'expected_daily_minutes'],
                    'progress' => ['required_minutes', 'rendered_minutes', 'remaining_minutes', 'percentage'],
                    'pace' => ['status', 'remaining_scheduled_days', 'required_daily_minutes', 'expected_daily_minutes'],
                    'attention' => ['items', 'additional_count'],
                    'completion' => ['ready', 'remaining_minutes', 'incomplete_required_requirements', 'blockers'],
                ],
            ]);
    }
}
