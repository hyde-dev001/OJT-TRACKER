<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\CompanyInternNotifications;
use App\Services\WorkItemProofs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CompanyInternNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intern_sees_only_own_paginated_notifications_and_unread_count(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $notifications = app(CompanyInternNotifications::class);

        for ($index = 1; $index <= 11; $index++) {
            $notifications->deadline($this->taskFor($intern, "Own item {$index}"), $intern, 'due_today');
        }
        $notifications->deadline($this->taskFor($otherIntern, 'Private other item'), $otherIntern, 'due_today');

        $page = $this->internRequest($intern)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('unread_count', 11)
            ->assertJsonMissingPath('data.0.proof_path')
            ->assertJsonMissingPath('data.0.review_comment');
        $this->assertStringNotContainsString('Private other item', $page->getContent());

        $this->internRequest($intern)
            ->getJson('/api/notifications?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('unread_count', 11);
    }

    public function test_mark_one_and_mark_all_are_scoped_to_the_authenticated_user(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $notifications = app(CompanyInternNotifications::class);
        $notifications->deadline($this->taskFor($intern, 'Own item'), $intern, 'due_today');
        $notifications->deadline($this->taskFor($intern, 'Another own item'), $intern, 'overdue');
        $notifications->deadline($this->taskFor($otherIntern, 'Other item'), $otherIntern, 'due_today');

        $ownRows = DB::table('notifications')->where('notifiable_id', $intern->id)->orderBy('created_at')->get();
        $otherRow = DB::table('notifications')->where('notifiable_id', $otherIntern->id)->first();

        $this->internRequest($intern)
            ->patchJson("/api/notifications/{$ownRows[0]->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $ownRows[0]->id)
            ->assertJsonPath('data.read_at', fn ($value) => is_string($value));

        $this->internRequest($intern)
            ->patchJson("/api/notifications/{$otherRow->id}/read")
            ->assertNotFound();

        $this->internRequest($intern)
            ->postJson('/api/notifications/read-all')
            ->assertNoContent();

        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $intern->id)->whereNull('read_at')->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $otherIntern->id)->whereNull('read_at')->count());
    }

    public function test_personal_student_can_list_and_mark_own_notifications_read_without_deleting_history(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $studentInternship = Internship::factory()->create(['student_id' => $student->id, 'company_id' => null]);
        $otherInternship = Internship::factory()->create(['student_id' => $otherStudent->id, 'company_id' => null]);
        $notifications = app(CompanyInternNotifications::class);
        $notifications->deadline(
            Task::factory()->create(['internship_id' => $studentInternship->id, 'title' => 'My task']),
            $student,
            'due_today',
        );
        $notifications->deadline(
            Task::factory()->create(['internship_id' => $otherInternship->id, 'title' => 'Private task']),
            $otherStudent,
            'due_today',
        );

        $this->stateful()->actingAs($student)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.destination', '/student/tasks')
            ->assertJsonPath('unread_count', 1)
            ->assertDontSee('Private task');

        $this->stateful()->actingAs($student)->postJson('/api/notifications/read-all')->assertNoContent();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertNotNull(DB::table('notifications')->where('notifiable_id', $student->id)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('notifiable_id', $otherStudent->id)->value('read_at'));
    }

    public function test_duplicate_deadline_events_are_ignored_by_the_database_key(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);
        $task = $this->taskFor($intern, 'Due today');
        $notifications = app(CompanyInternNotifications::class);

        $notifications->deadline($task, $intern, 'due_today');
        $notifications->deadline($task, $intern, 'due_today');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_personal_item_created_due_today_notifies_student_once_after_success(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:00:00', 'Asia/Manila'));

        try {
            $student = User::factory()->create(['role' => 'student']);
            $internship = Internship::factory()->create(['student_id' => $student->id, 'company_id' => null]);
            $request = $this->stateful()->actingAs($student);

            $request->postJson('/api/student/tasks', [
                'internship_id' => $internship->id,
                'title' => 'Due today task',
                'due_date' => '2026-10-15',
            ])->assertCreated();
            $request->postJson('/api/student/requirements', [
                'internship_id' => $internship->id,
                'title' => 'Due today requirement',
                'due_date' => '2026-10-15',
                'is_required' => true,
            ])->assertCreated();

            $events = $this->notificationData($student);
            $this->assertSame(['due_today', 'due_today'], array_column($events, 'event'));
            $this->assertEqualsCanonicalizing(['/student/tasks', '/student/requirements'], array_column($events, 'destination'));

            Artisan::call('company-work-items:send-deadline-notifications');
            $this->assertDatabaseCount('notifications', 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_company_assignments_notify_only_the_assigned_intern_and_same_day_due_items(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $otherIntern = User::factory()->create(['role' => 'intern']);
        Internship::factory()->create(['student_id' => $intern->id, 'company_id' => $company->id]);
        Internship::factory()->create(['student_id' => $otherIntern->id, 'company_id' => $company->id]);

        $task = $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks", [
                'title' => 'Today task',
                'due_date' => today()->toDateString(),
            ])
            ->assertCreated()
            ->json('data');
        $requirement = $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements", [
                'title' => 'Tomorrow requirement',
                'due_date' => today()->addDay()->toDateString(),
            ])
            ->assertCreated()
            ->json('data');

        $internEvents = $this->notificationData($intern);
        $this->assertCount(3, $internEvents);
        $this->assertSame(
            ['assignment_created', 'due_today', 'assignment_created'],
            array_column($internEvents, 'event'),
        );
        $this->assertSame(
            "/intern/tasks?task_id={$task['id']}",
            $internEvents[0]['destination'],
        );
        $this->assertSame($task['id'], $internEvents[1]['item_id']);
        $this->assertSame($requirement['id'], $internEvents[2]['item_id']);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $otherIntern->id)->count());
    }

    public function test_submissions_and_resubmissions_notify_only_the_owning_company_holder(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create(['student_id' => $intern->id, 'company_id' => $company->id]);
        Internship::factory()->create(['student_id' => $otherIntern->id, 'company_id' => $otherCompany->id]);
        $task = Task::factory()->create(['internship_id' => $internship->id, 'title' => 'Intern task']);
        $requirement = Requirement::factory()->create(['internship_id' => $internship->id, 'title' => 'Intern requirement']);

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('task.pdf', 64, 'application/pdf'),
            ])
            ->assertOk();
        $this->internRequest($intern)
            ->post("/api/intern/requirements/{$requirement->id}/submission", [
                'proof' => UploadedFile::fake()->create('requirement.pdf', 64, 'application/pdf'),
            ])
            ->assertOk();

        $companyHolder = $company->accountHolder;
        $events = $this->notificationData($companyHolder);
        $this->assertCount(2, $events);
        $this->assertSame(['proof_submitted', 'proof_submitted'], array_column($events, 'event'));
        $this->assertSame(['/company/tasks', '/company/requirements'], array_map(
            fn (array $event): string => parse_url($event['destination'], PHP_URL_PATH),
            $events,
        ));
        $this->assertSame($intern->id, $events[0]['intern_id']);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $otherCompany->account_holder_id)->count());

        $task->update(['submission_status' => 'changes_requested']);
        $requirement->update(['submission_status' => 'changes_requested']);
        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('task-revised.pdf', 64, 'application/pdf'),
            ])
            ->assertOk();
        $this->internRequest($intern)
            ->post("/api/intern/requirements/{$requirement->id}/submission", [
                'proof' => UploadedFile::fake()->create('requirement-revised.pdf', 64, 'application/pdf'),
            ])
            ->assertOk();

        $this->assertSame(4, DB::table('notifications')->where('notifiable_id', $companyHolder->id)->count());
    }

    public function test_resubmission_with_same_saved_timestamp_uses_a_distinct_saved_proof_version(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create(['student_id' => $intern->id, 'company_id' => $company->id]);
        $task = Task::factory()->create([
            'internship_id' => $internship->id,
            'submitted_at' => now(),
            'proof_path' => 'work-item-proofs/first-random-name.pdf',
        ]);
        $notifications = app(CompanyInternNotifications::class);
        $companyHolder = $company->accountHolder;

        $notifications->proofSubmitted($task, $companyHolder);
        $task->update(['proof_path' => 'work-item-proofs/second-random-name.pdf']);
        $notifications->proofSubmitted($task->refresh(), $companyHolder);

        $this->assertSame(2, DB::table('notifications')->where('notifiable_id', $companyHolder->id)->count());
    }

    public function test_reviews_notify_the_intern_and_failed_reviews_do_not_create_events(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create(['student_id' => $intern->id, 'company_id' => $company->id]);
        $task = Task::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Approved task',
            'submission_status' => 'pending_review',
        ]);
        $requirement = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Changes requested requirement',
            'submission_status' => 'pending_review',
        ]);
        $notSubmitted = Task::factory()->create(['internship_id' => $internship->id]);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/approve", [])
            ->assertOk();
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements/{$requirement->id}/request-changes", [
                'comment' => 'Please upload a clearer copy.',
            ])
            ->assertOk();
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$notSubmitted->id}/approve", [])
            ->assertStatus(409);

        $events = $this->notificationData($intern);
        $this->assertSame(['approved', 'changes_requested'], array_column($events, 'event'));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $company->account_holder_id)->count());
        $this->assertSame('completed', $task->refresh()->status);
        $this->assertSame('changes_requested', $requirement->refresh()->submission_status);
    }

    public function test_invalid_proof_and_rolled_back_proof_submission_create_no_notifications(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create(['student_id' => $intern->id, 'company_id' => $company->id]);
        $task = Task::factory()->create(['internship_id' => $internship->id]);

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('wrong.txt', 64, 'text/plain'),
            ])
            ->assertUnprocessable();

        try {
            app(WorkItemProofs::class)->replace(
                $internship->tasks(),
                $task->id,
                UploadedFile::fake()->create('rollback.pdf', 64, 'application/pdf'),
                null,
                function (Task $savedTask) use ($company): void {
                    app(CompanyInternNotifications::class)->proofSubmitted($savedTask, $company->accountHolder);
                    throw new RuntimeException('Force the submission transaction to roll back.');
                },
            );
            $this->fail('The callback exception should roll back the submission.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Force the submission transaction to roll back.', $exception->getMessage());
        }

        $this->assertSame('not_submitted', $task->refresh()->submission_status);
        $this->assertNull($task->proof_path);
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_deadline_command_handles_due_today_overdue_catch_up_and_actionable_states_idempotently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00:00', 'Asia/Manila'));

        try {
            $company = Company::factory()->create();
            $intern = User::factory()->create(['role' => 'intern']);
            $internship = Internship::factory()->create([
                'student_id' => $intern->id,
                'company_id' => $company->id,
            ]);
            $today = today('Asia/Manila')->toDateString();
            $yesterday = today('Asia/Manila')->subDay()->toDateString();
            $oldDueDate = today('Asia/Manila')->subDays(5)->toDateString();
            $tomorrow = today('Asia/Manila')->addDay()->toDateString();

            $todayTask = Task::factory()->create(['internship_id' => $internship->id, 'due_date' => $today]);
            $alreadyNotifiedToday = Task::factory()->create(['internship_id' => $internship->id, 'due_date' => $today]);
            Task::factory()->create(['internship_id' => $internship->id, 'due_date' => $yesterday]);
            Requirement::factory()->create([
                'internship_id' => $internship->id,
                'due_date' => $oldDueDate,
                'submission_status' => 'changes_requested',
            ]);
            Task::factory()->create([
                'internship_id' => $internship->id,
                'due_date' => $today,
                'submission_status' => 'pending_review',
            ]);
            Task::factory()->create([
                'internship_id' => $internship->id,
                'due_date' => $today,
                'status' => 'completed',
            ]);
            Requirement::factory()->create([
                'internship_id' => $internship->id,
                'due_date' => $today,
                'submission_status' => 'approved',
                'status' => 'completed',
            ]);
            Task::factory()->create(['internship_id' => $internship->id, 'due_date' => null]);
            Task::factory()->create(['internship_id' => $internship->id, 'due_date' => $tomorrow]);

            app(CompanyInternNotifications::class)->deadline($alreadyNotifiedToday, $intern, 'due_today');

            $this->assertSame(0, Artisan::call('company-work-items:send-deadline-notifications'));
            $this->assertSame(0, Artisan::call('company-work-items:send-deadline-notifications'));

            $events = $this->notificationData($intern);
            $this->assertSame(['due_today', 'due_today', 'overdue', 'overdue'], array_column($events, 'event'));
            $this->assertSame(2, count(array_filter($events, fn (array $event): bool => $event['event'] === 'due_today')));
            $this->assertSame(2, count(array_filter($events, fn (array $event): bool => $event['event'] === 'overdue')));
            $this->assertSame(4, DB::table('notifications')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_deadline_command_notifies_personal_students_about_missed_hours_and_actionable_due_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00:00', 'Asia/Manila'));

        try {
            $student = User::factory()->create(['role' => 'student']);
            $internship = Internship::factory()->create([
                'student_id' => $student->id,
                'company_id' => null,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-30',
                'work_days' => [3],
            ]);
            $workedStudent = User::factory()->create(['role' => 'student']);
            $workedInternship = Internship::factory()->create([
                'student_id' => $workedStudent->id,
                'company_id' => null,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-30',
                'work_days' => [3],
            ]);
            WorkLog::factory()->create(['internship_id' => $workedInternship->id, 'work_date' => '2026-10-14']);
            $unscheduledStudent = User::factory()->create(['role' => 'student']);
            Internship::factory()->create([
                'student_id' => $unscheduledStudent->id,
                'company_id' => null,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-30',
                'work_days' => [2],
            ]);

            Task::factory()->create([
                'internship_id' => $internship->id,
                'title' => 'Due today task',
                'due_date' => '2026-10-15',
            ]);
            Requirement::factory()->create([
                'internship_id' => $internship->id,
                'title' => 'Overdue requirement',
                'due_date' => '2026-10-14',
            ]);
            Task::factory()->create([
                'internship_id' => $internship->id,
                'title' => 'Completed task',
                'due_date' => '2026-10-15',
                'status' => 'completed',
            ]);

            $this->assertSame(0, Artisan::call('company-work-items:send-deadline-notifications'));
            $this->assertSame(0, Artisan::call('company-work-items:send-deadline-notifications'));

            $events = $this->notificationData($student);
            $eventsByType = collect($events)->keyBy('event');
            $this->assertEqualsCanonicalizing(['missed_work_hours', 'due_today', 'overdue'], array_column($events, 'event'));
            $this->assertSame('/student/work-hours', $eventsByType->get('missed_work_hours')['destination']);
            $this->assertStringContainsString('2026-10-14', $eventsByType->get('missed_work_hours')['message']);
            $this->assertSame('/student/tasks', $eventsByType->get('due_today')['destination']);
            $this->assertSame('/student/requirements', $eventsByType->get('overdue')['destination']);
            $this->assertSame([], $this->notificationData($workedStudent));
            $this->assertSame([], $this->notificationData($unscheduledStudent));
            $this->assertSame(3, DB::table('notifications')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_deadline_command_is_scheduled_for_manila_nine_am_without_overlap(): void
    {
        Artisan::call('schedule:list');
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'company-work-items:send-deadline-notifications'));

        $this->assertNotNull($event);
        $this->assertSame('0 9 * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_notification_detail_reads_are_scoped_and_never_expose_private_proof_paths(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $sameCompanyOtherIntern = User::factory()->create(['role' => 'intern']);
        $otherCompanyIntern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create(['student_id' => $intern->id, 'company_id' => $company->id]);
        $sameCompanyOtherInternship = Internship::factory()->create([
            'student_id' => $sameCompanyOtherIntern->id,
            'company_id' => $company->id,
        ]);
        $otherCompanyInternship = Internship::factory()->create([
            'student_id' => $otherCompanyIntern->id,
            'company_id' => $otherCompany->id,
        ]);
        $task = Task::factory()->create([
            'internship_id' => $internship->id,
            'proof_path' => 'work-item-proofs/private-task.pdf',
        ]);
        $sameCompanyTask = Task::factory()->create(['internship_id' => $sameCompanyOtherInternship->id]);
        $otherCompanyTask = Task::factory()->create(['internship_id' => $otherCompanyInternship->id]);
        $requirement = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'proof_path' => 'work-item-proofs/private-requirement.pdf',
        ]);
        $sameCompanyRequirement = Requirement::factory()->create(['internship_id' => $sameCompanyOtherInternship->id]);
        $otherCompanyRequirement = Requirement::factory()->create(['internship_id' => $otherCompanyInternship->id]);

        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.proof_available', true)
            ->assertJsonMissingPath('data.proof_path');
        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/tasks/{$sameCompanyTask->id}")
            ->assertNotFound();
        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$otherCompanyIntern->id}/tasks/{$otherCompanyTask->id}")
            ->assertNotFound();
        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/requirements/{$requirement->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $requirement->id)
            ->assertJsonPath('data.proof_available', true)
            ->assertJsonMissingPath('data.proof_path');
        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/requirements/{$sameCompanyRequirement->id}")
            ->assertNotFound();
        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$otherCompanyIntern->id}/requirements/{$otherCompanyRequirement->id}")
            ->assertNotFound();

        $this->internRequest($intern)
            ->getJson("/api/intern/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonMissingPath('data.proof_path');
        $this->internRequest($intern)
            ->getJson("/api/intern/tasks/{$sameCompanyTask->id}")
            ->assertNotFound();
        $this->internRequest($intern)
            ->getJson("/api/intern/requirements/{$requirement->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $requirement->id)
            ->assertJsonMissingPath('data.proof_path');
        $this->internRequest($intern)
            ->getJson("/api/intern/requirements/{$otherCompanyRequirement->id}")
            ->assertNotFound();
    }

    private function taskFor(User $intern, string $title): Task
    {
        $company = Company::factory()->create();
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);

        return Task::factory()->create([
            'internship_id' => $internship->id,
            'title' => $title,
            'due_date' => today()->toDateString(),
        ]);
    }

    private function internRequest(User $intern): self
    {
        return $this->stateful()->actingAs($intern)->withHeader('Accept', 'application/json');
    }

    private function companyRequest(Company $company): self
    {
        return $this->stateful()->actingAs($company->accountHolder)->withHeader('Accept', 'application/json');
    }

    /** @return list<array<string, mixed>> */
    private function notificationData(User $recipient): array
    {
        return DB::table('notifications')
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (object $notification): array => json_decode($notification->data, true))
            ->all();
    }
}
