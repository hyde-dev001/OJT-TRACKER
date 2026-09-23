<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_a_task_for_their_internship(): void
    {
        [$student, , $internship] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/tasks', $this->payload($internship))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Prepare weekly report')
            ->assertJsonPath('data.status', 'to_do');

        $this->assertDatabaseHas('tasks', [
            'internship_id' => $internship->id,
            'title' => 'Prepare weekly report',
        ]);
    }

    public function test_task_due_date_must_stay_within_the_internship_period(): void
    {
        [$student, , $internship] = $this->context();
        $internship->update([
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(5),
        ]);

        foreach ([today()->subDays(6), today()->addDays(6)] as $dueDate) {
            $this->studentRequest($student)
                ->postJson('/api/student/tasks', $this->payload($internship, ['due_date' => $dueDate->toDateString()]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('due_date');
        }
    }

    public function test_student_cannot_create_a_task_for_another_internship(): void
    {
        [$student, , , $otherInternship] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/tasks', $this->payload($otherInternship))
            ->assertForbidden();
    }

    public function test_student_sees_only_own_tasks(): void
    {
        [$student, , $internship, $otherInternship] = $this->context();
        $visible = Task::factory()->create(['internship_id' => $internship->id, 'title' => 'Visible task']);
        Task::factory()->create(['internship_id' => $otherInternship->id, 'title' => 'Hidden task']);

        $this->studentRequest($student)
            ->getJson('/api/student/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_student_tasks_are_paginated_and_status_filtered(): void
    {
        [$student, , $internship] = $this->context();

        for ($index = 1; $index <= 11; $index++) {
            Task::factory()->create([
                'internship_id' => $internship->id,
                'status' => 'to_do',
                'title' => "To-do task {$index}",
            ]);
        }
        Task::factory()->completed()->create([
            'internship_id' => $internship->id,
            'title' => 'Completed task',
        ]);

        $this->studentRequest($student)
            ->getJson('/api/student/tasks?page=2&filter=to_do')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('data.0.status', 'to_do');
    }

    public function test_task_filter_rejects_unknown_values(): void
    {
        [$student] = $this->context();

        $this->studentRequest($student)
            ->getJson('/api/student/tasks?filter=overdue')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    public function test_student_can_edit_and_delete_an_eligible_task(): void
    {
        [$student, , $internship] = $this->context();
        $task = Task::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->putJson("/api/student/tasks/{$task->id}", $this->payload($internship, ['title' => 'Updated task']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated task');

        $this->studentRequest($student)->deleteJson("/api/student/tasks/{$task->id}")->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_student_can_start_and_complete_a_task(): void
    {
        [$student, , $internship] = $this->context();
        $task = Task::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/student/tasks/{$task->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->studentRequest($student)
            ->postJson("/api/student/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_task_cannot_skip_in_progress(): void
    {
        [$student, , $internship] = $this->context();
        $task = Task::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/student/tasks/{$task->id}/complete")
            ->assertStatus(409);
    }

    public function test_completed_task_is_not_editable_or_deletable(): void
    {
        [$student, , $internship] = $this->context();
        $task = Task::factory()->completed()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->putJson("/api/student/tasks/{$task->id}", $this->payload($internship))
            ->assertStatus(409);
        $this->studentRequest($student)->deleteJson("/api/student/tasks/{$task->id}")->assertStatus(409);
    }

    public function test_old_task_review_routes_are_removed(): void
    {
        [$student, , $internship] = $this->context();
        $task = Task::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/coordinator/tasks/{$task->id}/complete")
            ->assertNotFound();
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
            'title' => 'Prepare weekly report',
            'description' => 'Summarize this week\'s OJT work.',
            'due_date' => '2026-09-30',
        ], $overrides);
    }

    private function studentRequest(User $student): self
    {
        return $this->stateful()->actingAs($student);
    }
}
