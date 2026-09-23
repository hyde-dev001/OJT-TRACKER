<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_a_requirement_for_their_internship(): void
    {
        [$student, , $internship] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/requirements', $this->payload($internship))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Signed training agreement')
            ->assertJsonPath('data.status', 'incomplete');
    }

    public function test_requirement_due_date_must_stay_within_the_internship_period(): void
    {
        [$student, , $internship] = $this->context();
        $internship->update([
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(5),
        ]);

        foreach ([today()->subDays(6), today()->addDays(6)] as $dueDate) {
            $this->studentRequest($student)
                ->postJson('/api/student/requirements', $this->payload($internship, ['due_date' => $dueDate->toDateString()]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('due_date');
        }
    }

    public function test_student_cannot_create_a_requirement_for_another_internship(): void
    {
        [$student, , , $otherInternship] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/requirements', $this->payload($otherInternship))
            ->assertForbidden();
    }

    public function test_student_sees_only_own_requirements(): void
    {
        [$student, , $internship, $otherInternship] = $this->context();
        $visible = Requirement::factory()->create(['internship_id' => $internship->id, 'title' => 'Visible requirement']);
        Requirement::factory()->create(['internship_id' => $otherInternship->id, 'title' => 'Hidden requirement']);

        $this->studentRequest($student)
            ->getJson('/api/student/requirements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_student_requirements_are_paginated_and_status_filtered(): void
    {
        [$student, , $internship] = $this->context();

        for ($index = 1; $index <= 11; $index++) {
            Requirement::factory()->create([
                'internship_id' => $internship->id,
                'status' => 'incomplete',
                'title' => "Incomplete requirement {$index}",
            ]);
        }
        Requirement::factory()->completed()->create([
            'internship_id' => $internship->id,
            'title' => 'Completed requirement',
        ]);

        $this->studentRequest($student)
            ->getJson('/api/student/requirements?page=2&filter=incomplete')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('data.0.status', 'incomplete');
    }

    public function test_overdue_requirement_filter_excludes_completed_items(): void
    {
        [$student, , $internship] = $this->context();
        Requirement::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Overdue incomplete requirement',
            'status' => 'incomplete',
            'due_date' => today()->subDay(),
        ]);
        Requirement::factory()->completed()->create([
            'internship_id' => $internship->id,
            'title' => 'Overdue completed requirement',
            'due_date' => today()->subDay(),
        ]);

        $this->studentRequest($student)
            ->getJson('/api/student/requirements?filter=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Overdue incomplete requirement')
            ->assertJsonPath('data.0.is_overdue', true);
    }

    public function test_requirement_filter_rejects_unknown_values(): void
    {
        [$student] = $this->context();

        $this->studentRequest($student)
            ->getJson('/api/student/requirements?filter=due_soon')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    public function test_student_can_edit_and_delete_a_requirement(): void
    {
        [$student, , $internship] = $this->context();
        $requirement = Requirement::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->putJson("/api/student/requirements/{$requirement->id}", $this->payload($internship, ['title' => 'Updated requirement']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated requirement');

        $this->studentRequest($student)->deleteJson("/api/student/requirements/{$requirement->id}")->assertNoContent();
        $this->assertDatabaseMissing('requirements', ['id' => $requirement->id]);
    }

    public function test_student_can_complete_and_reopen_a_requirement(): void
    {
        [$student, , $internship] = $this->context();
        $requirement = Requirement::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/student/requirements/{$requirement->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($requirement->fresh()->completed_at);

        $this->studentRequest($student)
            ->postJson("/api/student/requirements/{$requirement->id}/incomplete")
            ->assertOk()
            ->assertJsonPath('data.status', 'incomplete');
        $this->assertNull($requirement->fresh()->completed_at);
    }

    public function test_completed_requirement_cannot_be_edited_or_deleted(): void
    {
        [$student, , $internship] = $this->context();
        $requirement = Requirement::factory()->completed()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->putJson("/api/student/requirements/{$requirement->id}", $this->payload($internship, ['title' => 'Blocked edit']))
            ->assertStatus(409);
        $this->studentRequest($student)
            ->deleteJson("/api/student/requirements/{$requirement->id}")
            ->assertStatus(409);
    }

    public function test_reopened_requirement_can_be_edited_and_deleted(): void
    {
        [$student, , $internship] = $this->context();
        $requirement = Requirement::factory()->completed()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/student/requirements/{$requirement->id}/incomplete")
            ->assertOk()
            ->assertJsonPath('data.status', 'incomplete');
        $this->studentRequest($student)
            ->putJson("/api/student/requirements/{$requirement->id}", $this->payload($internship, ['title' => 'Editable again']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Editable again');
        $this->studentRequest($student)->deleteJson("/api/student/requirements/{$requirement->id}")->assertNoContent();
    }

    public function test_requirement_due_date_and_required_flag_persist(): void
    {
        [$student, , $internship] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/requirements', $this->payload($internship, [
                'due_date' => '2026-10-01',
                'is_required' => false,
                'student_notes' => 'Bring the signed copy.',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.due_date', '2026-10-01')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.student_notes', 'Bring the signed copy.');
    }

    public function test_old_requirement_review_routes_are_removed(): void
    {
        [$student, , $internship] = $this->context();
        $requirement = Requirement::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/coordinator/requirements/{$requirement->id}/approve")
            ->assertNotFound();
    }

    public function test_legacy_requirement_statuses_are_normalized_for_student_flow(): void
    {
        $student = User::factory()->create();
        $internship = Internship::factory()->create(['student_id' => $student->id]);
        $pending = Requirement::factory()->create(['internship_id' => $internship->id, 'status' => 'submitted']);
        $approved = Requirement::factory()->create(['internship_id' => $internship->id, 'status' => 'approved']);

        $migration = require database_path('migrations/2026_09_22_060000_normalize_legacy_requirement_statuses.php');
        $migration->up();

        $this->assertSame('not_completed', DB::table('requirements')->where('id', $pending->id)->value('status'));
        $this->assertSame('completed', DB::table('requirements')->where('id', $approved->id)->value('status'));
    }

    /** @return array{0: User, 1: User, 2: Internship, 3: Internship} */
    private function context(): array
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $internship = Internship::factory()->create(['student_id' => $student->id]);
        $otherInternship = Internship::factory()->create(['student_id' => $otherStudent->id]);

        return [$student, $otherStudent, $internship, $otherInternship];
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Internship $internship, array $overrides = []): array
    {
        return array_merge([
            'internship_id' => $internship->id,
            'title' => 'Signed training agreement',
            'description' => 'Provide the signed agreement.',
            'due_date' => '2026-09-30',
            'is_required' => true,
            'student_notes' => null,
        ], $overrides);
    }

    private function studentRequest(User $student): self
    {
        return $this->stateful()->actingAs($student);
    }
}
