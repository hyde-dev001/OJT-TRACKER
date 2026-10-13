<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternWorkLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_intern_can_view_own_paginated_logs_with_open_sessions_serialized_as_null(): void
    {
        [$intern, $internship] = $this->internshipContext();
        WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-13',
            'time_out' => null,
            'break_minutes' => 0,
            'rendered_minutes' => null,
            'status' => 'in_progress',
        ]);
        for ($day = 1; $day <= 10; $day++) {
            WorkLog::factory()->create([
                'internship_id' => $internship->id,
                'work_date' => sprintf('2026-10-%02d', $day),
            ]);
        }

        $this->internRequest($intern)
            ->getJson('/api/intern/work-logs?page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.work_date', '2026-10-13')
            ->assertJsonPath('data.0.status', 'in_progress')
            ->assertJsonPath('data.0.time_out', null)
            ->assertJsonPath('data.0.rendered_minutes', null)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 11);
    }

    public function test_intern_work_hours_include_only_completed_minutes_and_null_target_progress(): void
    {
        [$intern, $internship] = $this->internshipContext(['required_minutes' => null]);
        WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-12',
            'time_out' => null,
            'break_minutes' => 0,
            'rendered_minutes' => null,
            'status' => 'in_progress',
        ]);
        WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-11',
            'time_in' => '08:00',
            'time_out' => '10:00',
            'break_minutes' => 0,
            'rendered_minutes' => 120,
            'status' => 'completed',
        ]);

        $this->internRequest($intern)
            ->getJson('/api/intern/work-logs')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'in_progress');
        $this->internRequest($intern)
            ->getJson('/api/intern/internship')
            ->assertOk()
            ->assertJsonPath('data.progress.completed_minutes', 120)
            ->assertJsonPath('data.progress.required_minutes', null)
            ->assertJsonPath('data.progress.percentage', null);
    }

    public function test_intern_cannot_view_another_interns_work_logs(): void
    {
        [$intern] = $this->internshipContext();
        [, $otherInternship] = $this->internshipContext();
        WorkLog::factory()->create([
            'internship_id' => $otherInternship->id,
            'work_date' => '2026-10-12',
        ]);

        $this->internRequest($intern)
            ->getJson('/api/intern/work-logs')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_intern_work_logs_are_blocked_until_password_setup_is_complete(): void
    {
        [$intern] = $this->internshipContext(mustChangePassword: true);

        $this->internRequest($intern)
            ->getJson('/api/intern/work-logs')
            ->assertForbidden();
    }

    public function test_intern_cannot_write_work_logs_or_use_company_attendance_actions(): void
    {
        [$intern, $internship] = $this->internshipContext();
        $workLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-12',
        ]);

        $this->internRequest($intern)->postJson('/api/intern/work-logs')->assertStatus(405);
        $this->internRequest($intern)->putJson("/api/intern/work-logs/{$workLog->id}", [])->assertNotFound();
        $this->internRequest($intern)->deleteJson("/api/intern/work-logs/{$workLog->id}")->assertNotFound();
        $this->internRequest($intern)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
            ->assertForbidden();
        $this->internRequest($intern)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-out", ['break_minutes' => 0])
            ->assertForbidden();
        $this->internRequest($intern)
            ->putJson("/api/company/interns/{$intern->id}/work-logs/{$workLog->id}", [])
            ->assertForbidden();
    }

    public function test_personal_student_cannot_use_intern_work_log_endpoint(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->internRequest($student)
            ->getJson('/api/intern/work-logs')
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Internship} */
    private function internshipContext(array $overrides = [], bool $mustChangePassword = false): array
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create([
            'role' => 'intern',
            'must_change_password' => $mustChangePassword,
        ]);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'work_days' => [1, 2, 3, 4, 5, 6, 7],
            ...$overrides,
        ]);

        return [$intern, $internship];
    }

    private function internRequest(User $intern): self
    {
        return $this->stateful()->actingAs($intern);
    }
}
