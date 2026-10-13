<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Company;
use App\Models\Task;
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

    public function test_company_managed_intern_overview_uses_intern_links_and_keeps_unset_progress_unavailable(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'required_minutes' => null,
        ]);
        Task::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Submit a report',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->stateful()->actingAs($intern)
            ->getJson('/api/intern/overview')
            ->assertOk()
            ->assertJsonPath('data.progress.required_minutes', null)
            ->assertJsonPath('data.progress.percentage', null)
            ->assertJsonPath('data.pace.status', 'pace_unavailable')
            ->assertJsonPath('data.completion.ready', false)
            ->assertJsonPath('data.completion.blockers.0', 'required_hours_not_set')
            ->assertJsonPath('data.attention.items.0.href', '/intern/account')
            ->assertJsonPath('data.attention.items.1.href', '/intern/tasks');
    }
}
