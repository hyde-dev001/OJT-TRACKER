<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternWorkItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_intern_sees_only_current_company_items_and_cannot_submit_another_interns_item(): void
    {
        [$company, $intern, $internship] = $this->companyIntern();
        [, $otherIntern, $otherInternship] = $this->companyIntern();
        $task = Task::factory()->create(['internship_id' => $internship->id, 'title' => 'Own task']);
        $requirement = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'title' => 'Own requirement',
        ]);
        $otherTask = Task::factory()->create(['internship_id' => $otherInternship->id, 'title' => 'Other task']);
        Requirement::factory()->create([
            'internship_id' => $otherInternship->id,
            'title' => 'Other requirement',
        ]);

        $this->internRequest($intern)
            ->getJson('/api/intern/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task->id);

        $this->internRequest($intern)
            ->getJson('/api/intern/requirements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $requirement->id);

        Storage::fake('local');
        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$otherTask->id}/submission", [
                'proof' => UploadedFile::fake()->create('proof.pdf', 128, 'application/pdf'),
            ])
            ->assertNotFound();

        $this->assertSame('not_submitted', $otherTask->refresh()->submission_status);
        $this->assertSame($company->id, $internship->company_id);
    }

    public function test_company_student_and_password_setup_incomplete_accounts_cannot_use_intern_work_item_routes(): void
    {
        [, $setupIncompleteIntern] = $this->companyIntern(['must_change_password' => true]);
        $company = Company::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        $this->stateful()->actingAs($company->accountHolder)
            ->getJson('/api/intern/tasks')
            ->assertForbidden();

        $this->stateful()->actingAs($student)
            ->getJson('/api/intern/requirements')
            ->assertForbidden();

        $this->internRequest($setupIncompleteIntern)
            ->getJson('/api/intern/tasks')
            ->assertForbidden();

        $selfTrackedIntern = User::factory()->create(['role' => 'intern']);
        Internship::factory()->create([
            'student_id' => $selfTrackedIntern->id,
            'company_id' => null,
        ]);
        $this->internRequest($selfTrackedIntern)
            ->getJson('/api/intern/tasks')
            ->assertNotFound();
    }

    public function test_intern_can_submit_image_and_pdf_and_download_only_private_owned_proofs(): void
    {
        Storage::fake('local');
        [, $intern, $internship] = $this->companyIntern();
        [, $otherIntern, $otherInternship] = $this->companyIntern();
        $task = Task::factory()->create(['internship_id' => $internship->id]);
        $requirement = Requirement::factory()->create(['internship_id' => $internship->id]);
        $otherTask = Task::factory()->create(['internship_id' => $otherInternship->id]);

        $taskResponse = $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('report.png', 128, 'image/png'),
                'comment' => 'The report is attached.',
            ])
            ->assertOk()
            ->assertJsonPath('data.submission_status', 'pending_review')
            ->assertJsonPath('data.proof_available', true)
            ->assertJsonPath('data.submission_comment', 'The report is attached.')
            ->assertJsonMissingPath('data.proof_path');

        $requirementResponse = $this->internRequest($intern)
            ->post("/api/intern/requirements/{$requirement->id}/submission", [
                'proof' => UploadedFile::fake()->create('agreement.pdf', 128, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('data.submission_status', 'pending_review')
            ->assertJsonPath('data.proof_available', true)
            ->assertJsonPath('data.submission_comment', null)
            ->assertJsonPath('data.status', 'incomplete')
            ->assertJsonMissingPath('data.proof_path');

        $task->refresh();
        $requirement->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertSame('pending_review', $task->submission_status);
        $this->assertSame('pending_review', $requirement->submission_status);
        Storage::disk('local')->assertExists($task->proof_path);
        Storage::disk('local')->assertExists($requirement->proof_path);
        Storage::disk('public')->assertMissing($task->proof_path);
        Storage::disk('public')->assertMissing($requirement->proof_path);

        $taskDownload = $this->internRequest($intern)
            ->get("/api/intern/tasks/{$task->id}/proof")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment;', $taskDownload->headers->get('Content-Disposition'));
        $this->assertStringContainsString('task-proof.png', $taskDownload->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('report.png', $taskDownload->headers->get('Content-Disposition'));

        $this->internRequest($intern)
            ->get("/api/intern/requirements/{$requirement->id}/proof")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->internRequest($intern)
            ->get("/api/intern/tasks/{$otherTask->id}/proof")
            ->assertNotFound();
    }

    public function test_submission_validates_file_comment_and_state_without_losing_existing_proof(): void
    {
        Storage::fake('local');
        [, $intern, $internship] = $this->companyIntern();
        $task = Task::factory()->create(['internship_id' => $internship->id]);

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", ['comment' => 'No file'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proof');

        foreach ([
            UploadedFile::fake()->create('not-an-image.jpg', 128, 'text/plain'),
            UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf'),
        ] as $invalidFile) {
            $this->internRequest($intern)
                ->post("/api/intern/tasks/{$task->id}/submission", ['proof' => $invalidFile])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('proof');
        }

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('valid.pdf', 128, 'application/pdf'),
                'comment' => str_repeat('a', 5001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('valid.pdf', 128, 'application/pdf'),
            ])
            ->assertOk();
        $task->refresh();
        $oldProofPath = $task->proof_path;

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('replacement.pdf', 128, 'application/pdf'),
            ])
            ->assertStatus(409);

        Storage::disk('local')->assertExists($oldProofPath);
        $this->assertSame($oldProofPath, $task->refresh()->proof_path);

        $task->update(['submission_status' => 'approved']);
        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('approved-replacement.pdf', 128, 'application/pdf'),
            ])
            ->assertStatus(409);
        Storage::disk('local')->assertExists($oldProofPath);
        $this->assertSame($oldProofPath, $task->refresh()->proof_path);

        $task->update([
            'submission_status' => 'changes_requested',
            'review_comment' => 'Please revise this proof.',
            'reviewed_at' => now(),
        ]);
        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('still-not-an-image.jpg', 128, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proof');

        Storage::disk('local')->assertExists($oldProofPath);
        $this->assertSame($oldProofPath, $task->refresh()->proof_path);
    }

    public function test_resubmission_replaces_previous_proof_and_clears_review_metadata(): void
    {
        Storage::fake('local');
        [, $intern, $internship] = $this->companyIntern();
        $task = Task::factory()->create(['internship_id' => $internship->id]);
        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('first.png', 128, 'image/png'),
            ])
            ->assertOk();

        $task->refresh();
        $oldProofPath = $task->proof_path;
        $task->update([
            'submission_status' => 'changes_requested',
            'review_comment' => 'Please update the screenshot.',
            'reviewed_at' => now(),
        ]);

        $this->internRequest($intern)
            ->post("/api/intern/tasks/{$task->id}/submission", [
                'proof' => UploadedFile::fake()->create('second.png', 128, 'image/png'),
                'comment' => 'Updated screenshot attached.',
            ])
            ->assertOk()
            ->assertJsonPath('data.submission_status', 'pending_review')
            ->assertJsonPath('data.review_comment', null)
            ->assertJsonPath('data.reviewed_at', null);

        $task->refresh();
        $this->assertNotSame($oldProofPath, $task->proof_path);
        Storage::disk('local')->assertMissing($oldProofPath);
        Storage::disk('local')->assertExists($task->proof_path);
        $this->assertNull($task->review_comment);
        $this->assertNull($task->reviewed_at);
        $this->assertSame('Updated screenshot attached.', $task->submission_comment);
    }

    /** @return array{Company, User, Internship} */
    private function companyIntern(array $userOverrides = []): array
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create([
            'role' => 'intern',
            ...$userOverrides,
        ]);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);

        return [$company, $intern, $internship];
    }

    private function internRequest(User $intern): self
    {
        return $this->stateful()->actingAs($intern)->withHeader('Accept', 'application/json');
    }
}
