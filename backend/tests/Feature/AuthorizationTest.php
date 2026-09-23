<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_access_only_their_internship_and_records(): void
    {
        [$student, $otherStudent, $internship, $otherInternship] = $this->context();
        $ownLog = WorkLog::factory()->create(['internship_id' => $internship->id]);
        $otherLog = WorkLog::factory()->create(['internship_id' => $otherInternship->id]);
        $ownTask = Task::factory()->create(['internship_id' => $internship->id]);
        $otherTask = Task::factory()->create(['internship_id' => $otherInternship->id]);
        $ownRequirement = Requirement::factory()->create(['internship_id' => $internship->id]);
        $otherRequirement = Requirement::factory()->create(['internship_id' => $otherInternship->id]);

        $this->assertTrue(Gate::forUser($student)->allows('view', $internship));
        $this->assertFalse(Gate::forUser($student)->allows('view', $otherInternship));
        $this->assertTrue(Gate::forUser($student)->allows('view', $ownLog));
        $this->assertFalse(Gate::forUser($student)->allows('view', $otherLog));
        $this->assertTrue(Gate::forUser($student)->allows('view', $ownTask));
        $this->assertFalse(Gate::forUser($student)->allows('view', $otherTask));
        $this->assertTrue(Gate::forUser($student)->allows('view', $ownRequirement));
        $this->assertFalse(Gate::forUser($student)->allows('view', $otherRequirement));
        $this->assertNotSame($student->id, $otherStudent->id);
    }

    public function test_student_cannot_use_another_students_records_over_http(): void
    {
        [$student, , , $otherInternship] = $this->context();
        $otherLog = WorkLog::factory()->create(['internship_id' => $otherInternship->id]);
        $otherTask = Task::factory()->create(['internship_id' => $otherInternship->id]);
        $otherRequirement = Requirement::factory()->create(['internship_id' => $otherInternship->id]);

        $this->studentRequest($student)->getJson("/api/student/work-logs/{$otherLog->id}")->assertForbidden();
        $this->studentRequest($student)->getJson("/api/student/tasks/{$otherTask->id}")->assertForbidden();
        $this->studentRequest($student)->getJson("/api/student/requirements/{$otherRequirement->id}")->assertForbidden();
    }

    public function test_student_cannot_update_delete_or_transition_another_students_records(): void
    {
        [$student, , , $otherInternship] = $this->context();
        $otherTask = Task::factory()->create(['internship_id' => $otherInternship->id]);
        $otherRequirement = Requirement::factory()->create(['internship_id' => $otherInternship->id]);

        $this->studentRequest($student)
            ->putJson("/api/student/tasks/{$otherTask->id}", [
                'internship_id' => $otherInternship->id,
                'title' => 'Hijacked task',
                'due_date' => today()->addDay()->toDateString(),
            ])
            ->assertForbidden();
        $this->studentRequest($student)->deleteJson("/api/student/tasks/{$otherTask->id}")->assertForbidden();
        $this->studentRequest($student)->postJson("/api/student/tasks/{$otherTask->id}/start")->assertForbidden();
        $this->studentRequest($student)->postJson("/api/student/tasks/{$otherTask->id}/complete")->assertForbidden();

        $this->studentRequest($student)
            ->putJson("/api/student/requirements/{$otherRequirement->id}", [
                'internship_id' => $otherInternship->id,
                'title' => 'Hijacked requirement',
                'is_required' => true,
            ])
            ->assertForbidden();
        $this->studentRequest($student)->deleteJson("/api/student/requirements/{$otherRequirement->id}")->assertForbidden();
        $this->studentRequest($student)->postJson("/api/student/requirements/{$otherRequirement->id}/complete")->assertForbidden();
        $this->studentRequest($student)->postJson("/api/student/requirements/{$otherRequirement->id}/incomplete")->assertForbidden();
    }

    public function test_student_features_use_the_latest_internship_consistently(): void
    {
        $student = User::factory()->create();
        $olderInternship = Internship::factory()->create(['student_id' => $student->id]);
        $currentInternship = Internship::factory()->create(['student_id' => $student->id]);
        $currentTask = Task::factory()->create(['internship_id' => $currentInternship->id]);
        Task::factory()->create(['internship_id' => $olderInternship->id]);
        $currentRequirement = Requirement::factory()->create(['internship_id' => $currentInternship->id]);
        Requirement::factory()->create(['internship_id' => $olderInternship->id]);

        $this->studentRequest($student)
            ->getJson('/api/student/tasks')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentTask->id);

        $this->studentRequest($student)
            ->getJson('/api/student/requirements')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentRequirement->id);

        $this->studentRequest($student)
            ->postJson('/api/student/tasks', [
                'internship_id' => $olderInternship->id,
                'title' => 'Current task',
            ])
            ->assertCreated()
            ->assertJsonPath('data.internship_id', $currentInternship->id);

        $this->studentRequest($student)
            ->postJson('/api/student/requirements', [
                'internship_id' => $olderInternship->id,
                'title' => 'Current requirement',
                'is_required' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.internship_id', $currentInternship->id);
    }

    public function test_coordinator_routes_are_removed(): void
    {
        $student = User::factory()->create();

        $this->studentRequest($student)
            ->getJson('/api/coordinator/work-logs')
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

    private function studentRequest(User $student): self
    {
        return $this->stateful()->actingAs($student);
    }
}
