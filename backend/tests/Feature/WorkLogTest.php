<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_creates_a_completed_log_with_server_calculated_minutes(): void
    {
        [$student, , $internship] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.rendered_minutes', 480)
            ->assertJsonMissingPath('data.reviewer_remarks');

        $this->assertDatabaseHas('work_logs', [
            'internship_id' => $internship->id,
            'rendered_minutes' => 480,
            'status' => 'completed',
        ]);
    }

    public function test_client_rendered_minutes_and_status_are_not_trusted(): void
    {
        [$student, , $internship] = $this->context();

        $response = $this->studentRequest($student)->postJson('/api/student/work-logs', [
            ...$this->payload(),
            'rendered_minutes' => 9999,
            'status' => 'completed',
        ]);

        $response->assertCreated()->assertJsonPath('data.rendered_minutes', 480)->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('work_logs', [
            'internship_id' => $internship->id,
            'rendered_minutes' => 480,
            'status' => 'completed',
        ]);
    }

    public function test_time_out_must_be_after_time_in(): void
    {
        [$student] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload([
                'time_in' => '17:00',
                'time_out' => '08:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_out');
    }

    public function test_break_cannot_exceed_session_minutes(): void
    {
        [$student] = $this->context();

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload([
                'time_in' => '08:00',
                'time_out' => '09:00',
                'break_minutes' => 60,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('break_minutes');
    }

    public function test_work_date_is_unique_per_internship(): void
    {
        [$student] = $this->context();
        $this->studentRequest($student)->postJson('/api/student/work-logs', $this->payload())->assertCreated();

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');
    }

    public function test_work_date_must_stay_within_the_internship_period(): void
    {
        [$student, , $internship] = $this->context();
        $startDate = today()->subDays(12);
        $endDate = today()->subDays(2);
        $internship->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        foreach ([$startDate->copy()->subDay(), $endDate->copy()->addDay()] as $workDate) {
            $this->studentRequest($student)
                ->postJson('/api/student/work-logs', $this->payload(['work_date' => $workDate->toDateString()]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('work_date');
            }
    }

    public function test_work_date_must_match_a_configured_ojt_workday(): void
    {
        [$student, , $internship] = $this->context();
        $internship->update(['work_days' => [1, 2, 3, 4, 5]]);
        $monday = today()->startOfWeek();

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload([
                'work_date' => $monday->copy()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload([
                'work_date' => $monday->toDateString(),
            ]))
            ->assertCreated();
    }

    public function test_existing_log_can_keep_its_date_when_that_day_is_no_longer_configured(): void
    {
        [$student, , $internship] = $this->context();
        $offDutyDate = today()->startOfWeek()->subDay();
        $internship->update(['work_days' => [1, 2, 3, 4, 5]]);
        $workLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => $offDutyDate,
        ]);

        $this->studentRequest($student)
            ->putJson("/api/student/work-logs/{$workLog->id}", $this->payload([
                'work_date' => $offDutyDate->toDateString(),
                'accomplishment_summary' => 'Updated without changing the date.',
            ]))
            ->assertOk();
    }

    public function test_today_is_accepted_and_future_work_date_is_rejected(): void
    {
        [$student, , $internship] = $this->context();
        $internship->update([
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(5),
        ]);
        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload(['work_date' => today()->toDateString()]))
            ->assertCreated();

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload(['work_date' => today()->addDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');
    }

    public function test_future_start_rejects_work_logs_even_when_the_date_is_today(): void
    {
        [$student, , $internship] = $this->context();
        $internship->update([
            'start_date' => today()->addDays(5),
            'end_date' => today()->addDays(20),
        ]);

        $this->studentRequest($student)
            ->postJson('/api/student/work-logs', $this->payload(['work_date' => today()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');
    }

    public function test_work_log_updates_reject_dates_outside_the_period_but_allow_historical_dates(): void
    {
        [$student, , $internship] = $this->context();
        $startDate = today()->subDays(12);
        $endDate = today()->subDays(2);
        $internship->update(['start_date' => $startDate, 'end_date' => $endDate]);
        $workLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => $startDate->copy()->addDay(),
        ]);

        foreach ([$startDate->copy()->subDay(), today()->addDay(), $endDate->copy()->addDay()] as $workDate) {
            $this->studentRequest($student)
                ->putJson("/api/student/work-logs/{$workLog->id}", $this->payload([
                    'work_date' => $workDate->toDateString(),
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('work_date');
        }

        $validDate = $startDate->copy()->addDays(2)->toDateString();
        $this->studentRequest($student)
            ->putJson("/api/student/work-logs/{$workLog->id}", $this->payload(['work_date' => $validDate]))
            ->assertOk()
            ->assertJsonPath('data.work_date', $validDate);
    }

    public function test_completed_log_counts_toward_progress_immediately(): void
    {
        [$student] = $this->context();
        $this->createWorkLog($student);

        $this->studentRequest($student)
            ->getJson('/api/student/internship')
            ->assertOk()
            ->assertJsonPath('data.progress.completed_minutes', 480);
    }

    public function test_completed_log_can_be_edited_and_deleted(): void
    {
        [$student, , $internship] = $this->context();
        $workLog = $this->createWorkLog($student);

        $this->studentRequest($student)
            ->putJson("/api/student/work-logs/{$workLog->id}", $this->payload([
                'work_date' => '2026-09-11',
                'accomplishment_summary' => 'Updated detail.',
            ]))
            ->assertOk()
            ->assertJsonPath('data.accomplishment_summary', 'Updated detail.')
            ->assertJsonPath('data.status', 'completed');

        $this->studentRequest($student)->deleteJson("/api/student/work-logs/{$workLog->id}")->assertNoContent();
        $this->assertDatabaseMissing('work_logs', ['id' => $workLog->id]);
        $this->assertSame(0, WorkLog::query()->where('internship_id', $internship->id)->count());
    }

    public function test_student_work_logs_are_paginated(): void
    {
        [$student, , $internship] = $this->context();
        WorkLog::factory()->completed()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-08-01',
        ]);

        for ($day = 2; $day <= 12; $day++) {
            WorkLog::factory()->create([
                'internship_id' => $internship->id,
                'work_date' => sprintf('2026-08-%02d', $day),
            ]);
        }

        $this->studentRequest($student)
            ->getJson('/api/student/work-logs?page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 12);

    }

    public function test_student_cannot_access_another_students_log(): void
    {
        [$student, , , $otherInternship] = $this->context();
        $workLog = WorkLog::factory()->create(['internship_id' => $otherInternship->id]);

        $this->studentRequest($student)->getJson("/api/student/work-logs/{$workLog->id}")->assertForbidden();
    }

    public function test_old_work_log_review_routes_are_removed(): void
    {
        [$student, , $internship] = $this->context();
        $workLog = WorkLog::factory()->create(['internship_id' => $internship->id]);

        $this->studentRequest($student)
            ->postJson("/api/coordinator/work-logs/{$workLog->id}/approve")
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

    private function createWorkLog(User $student): WorkLog
    {
        $this->studentRequest($student)->postJson('/api/student/work-logs', $this->payload())->assertCreated();

        return WorkLog::query()->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'work_date' => today()->subDays(10)->toDateString(),
            'time_in' => '08:00',
            'time_out' => '17:00',
            'break_minutes' => 60,
            'accomplishment_summary' => 'Completed assigned OJT activities.',
        ], $overrides);
    }

    private function studentRequest(User $student): self
    {
        return $this->stateful()->actingAs($student);
    }
}
