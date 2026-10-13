<?php

namespace Tests\Feature;

use App\Http\Resources\RequirementResource;
use App\Http\Resources\TaskResource;
use App\Models\Company;
use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyWorkItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_item_resources_expose_submission_defaults_without_private_paths(): void
    {
        $task = Task::factory()->create();
        $requirement = Requirement::factory()->create();

        $this->assertSame('not_submitted', DB::table('tasks')->where('id', $task->id)->value('submission_status'));

        $taskPayload = (new TaskResource($task))->toArray(request());
        $requirementPayload = (new RequirementResource($requirement))->toArray(request());

        $this->assertSame('not_submitted', $taskPayload['submission_status']);
        $this->assertFalse($taskPayload['proof_available']);
        $this->assertArrayNotHasKey('proof_path', $taskPayload);
        $this->assertSame('not_submitted', $requirementPayload['submission_status']);
        $this->assertFalse($requirementPayload['proof_available']);
        $this->assertArrayNotHasKey('proof_path', $requirementPayload);
    }

    public function test_company_can_create_assignments_for_its_intern_without_trusting_client_ownership_or_state(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(5),
        ]);
        $otherInternship = Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks", [
                'title' => 'Prepare weekly report',
                'description' => 'Summarize assigned OJT work.',
                'due_date' => today()->toDateString(),
                'company_id' => $otherCompany->id,
                'internship_id' => $otherInternship->id,
                'status' => 'completed',
                'submission_status' => 'approved',
                'proof_path' => 'public/forged.pdf',
            ])
            ->assertCreated()
            ->assertJsonPath('data.internship_id', $internship->id)
            ->assertJsonPath('data.status', 'to_do')
            ->assertJsonPath('data.submission_status', 'not_submitted')
            ->assertJsonPath('data.proof_available', false)
            ->assertJsonMissingPath('data.proof_path');

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements", [
                'title' => 'Submit agreement',
                'due_date' => today()->toDateString(),
                'is_required' => false,
                'internship_id' => $otherInternship->id,
                'status' => 'completed',
                'submission_status' => 'approved',
            ])
            ->assertCreated()
            ->assertJsonPath('data.internship_id', $internship->id)
            ->assertJsonPath('data.status', 'incomplete')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.submission_status', 'not_submitted');

        $this->assertDatabaseHas('tasks', [
            'internship_id' => $internship->id,
            'title' => 'Prepare weekly report',
            'status' => 'to_do',
            'proof_path' => null,
        ]);
        $this->assertDatabaseHas('requirements', [
            'internship_id' => $internship->id,
            'title' => 'Submit agreement',
            'status' => 'incomplete',
            'is_required' => false,
        ]);
    }

    public function test_company_assignment_lists_are_scoped_and_paginated(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);

        Task::factory()->count(11)->create(['internship_id' => $internship->id]);
        Requirement::factory()->count(11)->create(['internship_id' => $internship->id]);

        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/tasks")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1);

        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$intern->id}/requirements")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_company_cannot_access_another_company_intern_or_use_company_write_routes_as_another_role(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherIntern = User::factory()->create(['role' => 'intern']);
        Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->companyRequest($company)
            ->getJson("/api/company/interns/{$otherIntern->id}/tasks")
            ->assertNotFound();

        $student = User::factory()->create(['role' => 'student']);
        $this->stateful()->actingAs($student)
            ->postJson("/api/company/interns/{$otherIntern->id}/tasks", ['title' => 'No access'])
            ->assertForbidden();

        $this->stateful()->actingAs($otherIntern)
            ->postJson("/api/company/interns/{$otherIntern->id}/requirements", ['title' => 'No access'])
            ->assertForbidden();
    }

    public function test_company_assignment_due_dates_must_be_inside_placement_dates(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(5),
        ]);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks", [
                'title' => 'Past due',
                'due_date' => today()->subDays(6)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements", [
                'title' => 'Too late',
                'due_date' => today()->addDays(6)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    public function test_company_can_request_changes_or_approve_only_pending_items(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);
        $task = Task::factory()->create([
            'internship_id' => $internship->id,
            'status' => 'in_progress',
            'submission_status' => 'pending_review',
        ]);
        $requirement = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'submission_status' => 'pending_review',
        ]);
        $unsubmittedTask = Task::factory()->create(['internship_id' => $internship->id]);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/request-changes", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/request-changes", [
                'comment' => 'Please add the missing date range.',
                'status' => 'completed',
                'submission_status' => 'approved',
                'completed_at' => now()->toISOString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.submission_status', 'changes_requested')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.review_comment', 'Please add the missing date range.');

        $this->assertNull($task->refresh()->completed_at);
        $this->assertNotNull($task->reviewed_at);

        $task->update(['submission_status' => 'pending_review']);
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/approve", [
                'submission_status' => 'changes_requested',
                'status' => 'to_do',
            ])
            ->assertOk()
            ->assertJsonPath('data.submission_status', 'approved')
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($task->refresh()->completed_at);
        $this->assertNotNull($task->reviewed_at);
        $this->assertNull($task->review_comment);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/request-changes", [
                'comment' => 'Already approved.',
            ])
            ->assertStatus(409);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$unsubmittedTask->id}/approve", [])
            ->assertStatus(409);

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements/{$requirement->id}/approve", [
                'comment' => 'Agreement accepted.',
            ])
            ->assertOk()
            ->assertJsonPath('data.submission_status', 'approved')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.review_comment', 'Agreement accepted.');

        $this->assertNotNull($requirement->refresh()->completed_at);
        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements/{$requirement->id}/request-changes", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    public function test_company_proof_downloads_are_scoped_to_selected_intern_and_company(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $otherIntern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);
        $otherInternship = Internship::factory()->create([
            'student_id' => $otherIntern->id,
            'company_id' => $otherCompany->id,
        ]);
        $proofPath = 'work-item-proofs/private-proof.pdf';
        $otherProofPath = 'work-item-proofs/other-proof.pdf';
        $requirementProofPath = 'work-item-proofs/private-requirement.pdf';
        $otherRequirementProofPath = 'work-item-proofs/other-requirement.pdf';
        Storage::disk('local')->put($proofPath, "%PDF-1.4\nproof\n%%EOF");
        Storage::disk('local')->put($otherProofPath, "%PDF-1.4\nother\n%%EOF");
        Storage::disk('local')->put($requirementProofPath, "%PDF-1.4\nreq\n%%EOF");
        Storage::disk('local')->put($otherRequirementProofPath, "%PDF-1.4\nother req\n%%EOF");
        $task = Task::factory()->create([
            'internship_id' => $internship->id,
            'submission_status' => 'pending_review',
            'proof_path' => $proofPath,
        ]);
        $otherTask = Task::factory()->create([
            'internship_id' => $otherInternship->id,
            'submission_status' => 'pending_review',
            'proof_path' => $otherProofPath,
        ]);
        $requirement = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'submission_status' => 'pending_review',
            'proof_path' => $requirementProofPath,
        ]);
        $otherRequirement = Requirement::factory()->create([
            'internship_id' => $otherInternship->id,
            'submission_status' => 'pending_review',
            'proof_path' => $otherRequirementProofPath,
        ]);

        $this->companyRequest($company)
            ->get("/api/company/interns/{$intern->id}/tasks/{$task->id}/proof")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->companyRequest($company)
            ->get("/api/company/interns/{$intern->id}/tasks/{$otherTask->id}/proof")
            ->assertNotFound();

        $this->companyRequest($company)
            ->get("/api/company/interns/{$otherIntern->id}/tasks/{$otherTask->id}/proof")
            ->assertNotFound();

        $this->companyRequest($company)
            ->get("/api/company/interns/{$intern->id}/requirements/{$requirement->id}/proof")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->companyRequest($company)
            ->get("/api/company/interns/{$intern->id}/requirements/{$otherRequirement->id}/proof")
            ->assertNotFound();

        $this->companyRequest($company)
            ->get("/api/company/interns/{$otherIntern->id}/requirements/{$otherRequirement->id}/proof")
            ->assertNotFound();
    }

    public function test_only_company_accounts_can_review_company_work_items(): void
    {
        $company = Company::factory()->create();
        $intern = User::factory()->create(['role' => 'intern']);
        $internship = Internship::factory()->create([
            'student_id' => $intern->id,
            'company_id' => $company->id,
        ]);
        $task = Task::factory()->create([
            'internship_id' => $internship->id,
            'submission_status' => 'pending_review',
        ]);
        $requirement = Requirement::factory()->create([
            'internship_id' => $internship->id,
            'submission_status' => 'pending_review',
        ]);

        $this->stateful()->actingAs(User::factory()->create(['role' => 'student']))
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/approve", [])
            ->assertForbidden();

        $this->stateful()->actingAs($intern)
            ->postJson("/api/company/interns/{$intern->id}/tasks/{$task->id}/approve", [])
            ->assertForbidden();

        $this->companyRequest($company)
            ->postJson("/api/company/interns/{$intern->id}/requirements/{$requirement->id}/request-changes", [
                'comment' => str_repeat('a', 5001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    private function companyRequest(Company $company): self
    {
        return $this->stateful()->actingAs($company->accountHolder);
    }
}
