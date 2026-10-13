<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyWorkLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_session_persists_without_time_out_or_rendered_minutes(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'required_minutes' => null,
        ]);

        $log = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-13',
            'time_out' => null,
            'break_minutes' => 0,
            'rendered_minutes' => null,
            'status' => 'in_progress',
        ]);

        $this->assertNull($log->refresh()->time_out);
        $this->assertNull($log->rendered_minutes);
        $this->assertSame('in_progress', $log->status);
    }

    public function test_database_rejects_duplicate_work_logs_for_an_internship_date(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);
        $attributes = [
            'internship_id' => $internship->id,
            'work_date' => '2026-10-13',
        ];

        WorkLog::factory()->create($attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        WorkLog::factory()->create($attributes);
    }

    public function test_company_time_in_uses_server_date_and_time_and_ignores_client_values(): void
    {
        [$company, $intern] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', config('app.timezone')));

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in", [
                'work_date' => '2030-01-01',
                'time_in' => '01:00',
                'status' => 'completed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.work_date', '2026-10-13')
            ->assertJsonPath('data.time_in', '08:00')
            ->assertJsonPath('data.time_out', null)
            ->assertJsonPath('data.rendered_minutes', null)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_company_cannot_start_a_second_session_for_the_same_day(): void
    {
        [$company, $intern] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', config('app.timezone')));
        $url = "/api/company/interns/{$intern->id}/work-logs/time-in";

        $this->companyRequest($company)->postJson($url)->assertCreated();
        $this->companyRequest($company)
            ->postJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');
    }

    public function test_company_can_time_out_with_zero_break_and_server_calculated_minutes(): void
    {
        [$company, $intern] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', config('app.timezone')));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
            ->assertCreated();

        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', config('app.timezone')));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-out", ['break_minutes' => 0])
            ->assertOk()
            ->assertJsonPath('data.time_out', '09:00')
            ->assertJsonPath('data.break_minutes', 0)
            ->assertJsonPath('data.rendered_minutes', 60)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_company_can_record_one_rendered_minute_but_not_a_break_equal_to_the_session(): void
    {
        [$company, $intern] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', config('app.timezone')));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
            ->assertCreated();
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', config('app.timezone')));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-out", ['break_minutes' => 59])
            ->assertOk()
            ->assertJsonPath('data.rendered_minutes', 1);

        $otherIntern = User::factory()->create(['role' => 'intern']);
        Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $company->id,
            ...$this->placement(),
        ]);
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', config('app.timezone')));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$otherIntern->id}/work-logs/time-in")
            ->assertCreated();
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', config('app.timezone')));

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$otherIntern->id}/work-logs/time-out", ['break_minutes' => 60])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('break_minutes');
    }

    public function test_company_can_edit_completed_logs_but_derived_and_ownership_fields_are_ignored(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();
        $otherCompany = Company::factory()->create();
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $otherInternship = Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $otherCompany->id,
            ...$this->placement(),
        ]);
        $workLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-12',
        ]);

        $this->companyRequest($company)
            ->putJson("/api/company/interns/{$intern->id}/work-logs/{$workLog->id}", [
                'work_date' => '2026-10-12',
                'time_in' => '08:15',
                'time_out' => '17:00',
                'break_minutes' => 30,
                'rendered_minutes' => 1,
                'status' => 'in_progress',
                'internship_id' => $otherInternship->id,
                'company_id' => $otherCompany->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.internship_id', $internship->id)
            ->assertJsonPath('data.rendered_minutes', 495)
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame($internship->id, $workLog->refresh()->internship_id);
        $this->assertSame(495, $workLog->rendered_minutes);
    }

    public function test_company_correction_rejects_invalid_time_order_and_break_duration(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();
        $workLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-12',
        ]);
        $url = "/api/company/interns/{$intern->id}/work-logs/{$workLog->id}";

        $this->companyRequest($company)
            ->putJson($url, [
                'work_date' => '2026-10-12',
                'time_in' => '09:00',
                'time_out' => '09:00',
                'break_minutes' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_out');

        $this->companyRequest($company)
            ->putJson($url, [
                'work_date' => '2026-10-12',
                'time_in' => '08:00',
                'time_out' => '09:00',
                'break_minutes' => 60,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('break_minutes');
    }

    public function test_company_cannot_time_out_without_a_current_open_session(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', config('app.timezone')));
        $url = "/api/company/interns/{$intern->id}/work-logs/time-out";

        $this->companyRequest($company)
            ->postJson($url, ['break_minutes' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_out');

        $staleLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-12',
            'time_out' => null,
            'break_minutes' => 0,
            'rendered_minutes' => null,
            'status' => 'in_progress',
        ]);

        $this->companyRequest($company)
            ->postJson($url, ['break_minutes' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_out');

        $this->assertNull($staleLog->refresh()->time_out);
    }

    public function test_company_time_in_obeys_placement_dates_and_work_days(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', config('app.timezone')));

        $internship->update(['start_date' => '2026-10-14']);
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');

        $internship->update([
            'start_date' => '2026-10-01',
            'work_days' => [1],
        ]);
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');
    }

    public function test_company_can_keep_an_existing_date_after_schedule_changes_but_new_dates_must_match(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();
        $workLog = WorkLog::factory()->create([
            'internship_id' => $internship->id,
            'work_date' => '2026-10-12',
        ]);
        $internship->update([
            'start_date' => '2026-10-13',
            'work_days' => [2],
        ]);
        $url = "/api/company/interns/{$intern->id}/work-logs/{$workLog->id}";

        $this->companyRequest($company)
            ->putJson($url, [
                'work_date' => '2026-10-12',
                'time_in' => '08:00',
                'time_out' => '09:00',
                'break_minutes' => 0,
            ])
            ->assertOk();

        $this->companyRequest($company)
            ->putJson($url, [
                'work_date' => '2026-10-14',
                'time_in' => '08:00',
                'time_out' => '09:00',
                'break_minutes' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');
    }

    public function test_company_time_out_rejects_crossing_manila_midnight_but_correction_can_close_same_day(): void
    {
        [$company, $intern] = $this->companyIntern();
        $this->travelTo(Carbon::parse('2026-10-13 23:50:00', 'Asia/Manila'));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
            ->assertCreated()
            ->assertJsonPath('data.time_in', '23:50');

        $this->travelTo(Carbon::parse('2026-10-14 00:05:00', 'Asia/Manila'));
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/work-logs/time-out", ['break_minutes' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_out');

        $workLog = WorkLog::query()->where('internship_id', $intern->currentInternship()->value('id'))->firstOrFail();
        $this->companyRequest($company)
            ->putJson("/api/company/interns/{$intern->id}/work-logs/{$workLog->id}", [
                'work_date' => '2026-10-13',
                'time_in' => '23:50',
                'time_out' => '23:59',
                'break_minutes' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.rendered_minutes', 9)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_company_work_log_list_is_newest_first_and_paginated(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();

        for ($day = 1; $day <= 11; $day++) {
            WorkLog::factory()->create([
                'internship_id' => $internship->id,
                'work_date' => sprintf('2026-10-%02d', $day),
            ]);
        }

        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/work-logs?page=1")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.work_date', '2026-10-11')
            ->assertJsonPath('meta.total', 11);
    }

    public function test_company_cannot_list_time_or_edit_another_companys_work_logs(): void
    {
        $company = Company::factory()->create();
        [$otherCompany, $otherIntern, $otherInternship] = $this->companyIntern();
        $workLog = WorkLog::factory()->create([
            'internship_id' => $otherInternship->id,
            'work_date' => '2026-10-12',
        ]);

        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$otherIntern->id}/work-logs")
            ->assertNotFound();
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$otherIntern->id}/work-logs/time-in")
            ->assertNotFound();
        $this->companyRequest($company)
            ->putJson("/api/company/interns/{$otherIntern->id}/work-logs/{$workLog->id}", [
                'work_date' => '2026-10-12',
                'time_in' => '08:00',
                'time_out' => '09:00',
                'break_minutes' => 0,
            ])
            ->assertNotFound();
    }

    /** @return array{0: Company, 1: User, 2: Internship} */
    private function companyIntern(array $overrides = []): array
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            ...$this->placement(),
            ...$overrides,
        ]);

        return [$company, $intern, $internship];
    }

    /** @return array<string, mixed> */
    private function placement(): array
    {
        return [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'work_days' => [1, 2, 3, 4, 5, 6, 7],
        ];
    }

    private function companyRequest(Company $company): self
    {
        return $this->stateful()->actingAs($company->accountHolder);
    }
}
