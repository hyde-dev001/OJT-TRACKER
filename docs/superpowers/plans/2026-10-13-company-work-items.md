# Company Tasks, Requirements, and Proof Review Implementation Plan

> **For agentic workers:** After user approval, execute this plan sequentially with the superpowers:executing-plans skill. Use one main agent, preserve existing uncommitted changes, and do not commit changes unless the user explicitly authorizes a commit.

**Goal:** Add company-managed tasks and requirements with private intern proof submissions and company review while preserving the existing personal student workflows.

**Architecture:** Reuse the existing tasks and requirements tables and internship ownership relationship. Add current-submission metadata to both item types, role-scoped company and intern endpoints, private proof download endpoints, and separate role-specific Vue pages. Keep student routes and state transitions unchanged.

**Tech Stack:** Laravel 12, PHP 8.2+, Sanctum, Eloquent, Laravel local filesystem, MySQL/MariaDB, Vue 3, JavaScript, Vue Router, Axios, Tailwind CSS, PHPUnit, Vitest, and Vue Test Utils.

**Spec:** docs/superpowers/specs/2026-10-13-company-work-items-design.md (approved for implementation 2026-10-13)

**Status:** Implemented

## Global Constraints

- Reuse the existing tasks and requirements tables.
- A company account holder selects an intern belonging to that company; the backend derives the intern's internship and ownership.
- Company account holders cannot edit or delete assignments in this slice.
- An intern submits one JPEG, PNG, WebP, or PDF proof file up to 10 MB with an optional comment.
- Proof files use the private local disk and are served only through authenticated, ownership-checked routes.
- Submission states are not_submitted, pending_review, changes_requested, and approved.
- Requesting changes requires a comment; approving accepts an optional comment.
- Approval completes the item; requesting changes leaves it open and permits resubmission.
- Resubmission replaces and removes the prior proof; submission history is not stored.
- Personal student task and requirement routes, screens, and workflow remain unchanged.
- Do not add new roles, notifications, public URLs, submission history, assignment edit/delete, dependencies, or commits.
- Use one main agent and execute steps sequentially as required by AGENTS.md.
- Use isolated SQLite feature tests and an isolated browser-test database; do not run destructive database commands against the user's local database.

## Review Focus

- **Forged ownership IDs:** request bodies cannot set company_id or internship_id, and route IDs must resolve through the authenticated company's intern relationship or the intern's current internship. Test cross-company and cross-intern IDs.
- **Spoofed or oversized proof:** validate both allowed content types and the 10 MB limit on the server; test a wrong MIME/file extension and a 10,241 KB upload.
- **Replacement failure and cleanup:** preserve the current proof if a new upload cannot be stored or persisted; verify a successful resubmission deletes the old private file and does not expose its path.
- **Invalid state races:** only one current submission may be pending review; serialize competing review/submission transitions and reject resubmitting pending or approved items.
- **Personal-flow leakage:** company item states must not change student Task/Requirement results, routes, filters, or completion behavior; retain the existing TaskTest and RequirementTest suites as regression checks.

---

## File Structure

### Backend

- Create backend/database/migrations/2026_10_13_000003_add_work_item_submission_fields.php for the current proof/review fields on tasks and requirements.
- Modify backend/app/Models/Task.php and Requirement.php for the new fields and timestamp casts; keep existing student state methods intact.
- Modify backend/app/Http/Resources/TaskResource.php and RequirementResource.php to return current submission status, comments, timestamps, and a proof_available boolean without returning proof_path.
- Create backend/app/Http/Controllers/Company/TaskController.php and RequirementController.php for company-scoped creation/listing, proof retrieval, and review actions.
- Create backend/app/Http/Controllers/Intern/TaskController.php and RequirementController.php for the authenticated intern's read-only lists, proof submission/resubmission, and proof retrieval.
- Create backend/app/Http/Requests/StoreCompanyTaskRequest.php, StoreCompanyRequirementRequest.php, SubmitInternTaskProofRequest.php, SubmitInternRequirementProofRequest.php, and ReviewCompanyWorkItemRequest.php for request validation.
- Modify backend/routes/api.php to add company and intern endpoints beneath the existing role and password-setup middleware.
- Create backend/tests/Feature/CompanyWorkItemTest.php and InternWorkItemTest.php. Keep TaskTest.php and RequirementTest.php as the personal-flow regression suites.

### Frontend

- Create frontend/src/views/company/CompanyTasksView.vue and CompanyRequirementsView.vue with intern selection, assignment creation, current status, proof download, and review actions.
- Create frontend/src/views/intern/InternTasksView.vue and InternRequirementsView.vue with assignment viewing, proof submission/resubmission, comments, and proof download.
- Modify frontend/src/services/ojt.js and ojt.test.js for list/create/review/submission calls and blob downloads, using the existing Axios client.
- Modify frontend/src/router/index.js and index.test.js for company and intern routes.
- Modify frontend/src/App.vue, App.test.js, frontend/src/components/MobileNavigationDrawer.vue, and MobileNavigationDrawer.test.js for role-specific navigation.
- Add a test beside each new view. Reuse existing page headers, field controls, pagination, status styles, and form feedback patterns where they fit.

### Documentation

- Update PROJECT_CONTEXT.md, DESIGN.md, AGENTS.md, and README.md to describe the company task/requirement and proof-review workflows after implementation.
- Keep those documentation edits out of the personal student feature descriptions except to clarify that the workflows remain separate.

---

### Task 1: Add current submission fields and resource output

**Files:**

- Create: backend/database/migrations/2026_10_13_000003_add_work_item_submission_fields.php
- Modify: backend/app/Models/Task.php
- Modify: backend/app/Models/Requirement.php
- Modify: backend/app/Http/Resources/TaskResource.php
- Modify: backend/app/Http/Resources/RequirementResource.php
- Test: backend/tests/Feature/CompanyWorkItemTest.php

**Interfaces:**

- Both existing tables gain submission_status (default not_submitted), proof_path (nullable), submission_comment (nullable text), review_comment (nullable text), submitted_at (nullable timestamp), and reviewed_at (nullable timestamp).
- Resources return the submission fields and proof_available, never the private proof path.
- Existing item status fields and student operations keep their current meaning.

- [x] **Step 1: Add a failing persistence/resource test.** Create one task and one requirement directly through their factories. Resolve TaskResource and RequirementResource directly rather than calling company routes, which are added in Task 2. Assert the default submission state is not_submitted, proof_available is false, and neither resource includes proof_path.

~~~php
$taskPayload = (new TaskResource($task))->toArray(request());
$requirementPayload = (new RequirementResource($requirement))->toArray(request());

$this->assertSame('not_submitted', $taskPayload['submission_status']);
$this->assertFalse($taskPayload['proof_available']);
$this->assertArrayNotHasKey('proof_path', $taskPayload);
$this->assertSame('not_submitted', $requirementPayload['submission_status']);
$this->assertFalse($requirementPayload['proof_available']);
$this->assertArrayNotHasKey('proof_path', $requirementPayload);
~~~

- [x] **Step 2: Run the new feature test and confirm the new fields/resource values are absent.**

Run from backend: php artisan test --compact tests/Feature/CompanyWorkItemTest.php

- [x] **Step 3: Add the additive migration and model casts.** Add the six nullable/defaulted fields to both tables. Add only the new fields to model fillable/casts; do not change Task::start/complete or Requirement::complete/incomplete.

~~~php
foreach (['tasks', 'requirements'] as $tableName) {
    Schema::table($tableName, function (Blueprint $table): void {
        $table->string('submission_status')->default('not_submitted');
        $table->string('proof_path')->nullable();
        $table->text('submission_comment')->nullable();
        $table->text('review_comment')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('reviewed_at')->nullable();
    });
}
~~~

- [x] **Step 4: Extend existing resources additively.** Return submission metadata and a boolean derived from proof_path presence. Do not serialize the storage path or client filename.

- [x] **Step 5: Run the focused new feature test plus the personal TaskTest and RequirementTest suites.**

Run from backend: php artisan test --compact tests/Feature/CompanyWorkItemTest.php tests/Feature/TaskTest.php tests/Feature/RequirementTest.php

Expected: all targeted tests pass and existing student responses/actions remain valid.

### Task 2: Add company assignment creation and listing

**Files:**

- Create: backend/app/Http/Controllers/Company/TaskController.php
- Create: backend/app/Http/Controllers/Company/RequirementController.php
- Create: backend/app/Http/Requests/StoreCompanyTaskRequest.php
- Create: backend/app/Http/Requests/StoreCompanyRequirementRequest.php
- Modify: backend/routes/api.php
- Test: backend/tests/Feature/CompanyWorkItemTest.php

**Interfaces:**

- GET and POST /company/interns/{intern}/tasks
- GET and POST /company/interns/{intern}/requirements
- Company list responses are paginated with the existing 10-row convention.
- Task creation accepts title, optional description, and optional due_date.
- Requirement creation accepts title, optional description, optional due_date, and optional is_required defaulting to true.
- Controllers set internship_id and initial item/submission states from the company-scoped internship. Request company_id, internship_id, status, and submission metadata are ignored or rejected.

- [x] **Step 1: Add failing company API tests.** Verify one company creates a task and requirement for its intern with server-set internship IDs and initial states. Verify a different company's intern returns 404, a student/intern cannot use company write routes, submitted ownership/state values cannot alter stored ownership, and due dates outside placement bounds return 422.

~~~php
$this->companyRequest($company)
    ->postJson("/api/company/interns/{$intern->id}/tasks", [
        'title' => 'Prepare weekly report',
        'description' => 'Summarize the assigned OJT work.',
        'due_date' => $internship->end_date->toDateString(),
        'internship_id' => $otherInternship->id,
        'status' => 'completed',
    ])
    ->assertCreated()
    ->assertJsonPath('data.internship_id', $internship->id)
    ->assertJsonPath('data.status', 'to_do')
    ->assertJsonPath('data.submission_status', 'not_submitted');
~~~

- [x] **Step 2: Run CompanyWorkItemTest and confirm the company endpoints are not found.**

Run from backend: php artisan test --compact tests/Feature/CompanyWorkItemTest.php

- [x] **Step 3: Add Form Requests.** Validate title as a required string up to 255 characters, description as optional text up to 5,000 characters, due_date as optional Y-m-d within the selected internship dates, and is_required as an optional boolean defaulting to true.

- [x] **Step 4: Add company controllers and routes.** Resolve the company from the authenticated account, resolve the selected user through that company's internships, and require role intern. Root list queries in the resolved internship. Set task status to to_do, requirement status to incomplete, and submission_status to not_submitted. Do not add assignment edit/delete routes.

~~~php
$internship = $request->user()->company()->firstOrFail()->internships()
    ->where('student_id', $intern->id)
    ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
    ->firstOrFail();
~~~

Creation uses the returned internship relationship:

~~~php
$task = $internship->tasks()->create([
    ...$request->safe()->only(['title', 'description', 'due_date']),
    'status' => 'to_do',
    'submission_status' => 'not_submitted',
]);
~~~

- [x] **Step 5: Run company assignment and personal task/requirement feature tests.**

Run from backend: php artisan test --compact tests/Feature/CompanyWorkItemTest.php tests/Feature/TaskTest.php tests/Feature/RequirementTest.php

### Task 3: Add intern assignment reads and private proof submission

**Files:**

- Create: backend/app/Http/Controllers/Intern/TaskController.php
- Create: backend/app/Http/Controllers/Intern/RequirementController.php
- Create: backend/app/Http/Requests/SubmitInternTaskProofRequest.php
- Create: backend/app/Http/Requests/SubmitInternRequirementProofRequest.php
- Modify: backend/routes/api.php
- Test: backend/tests/Feature/InternWorkItemTest.php

**Interfaces:**

- GET /intern/tasks and GET /intern/requirements return only the authenticated intern's current company-managed internship items.
- POST /intern/tasks/{task}/submission and POST /intern/requirements/{requirement}/submission accept multipart fields proof and optional comment.
- GET /intern/tasks/{task}/proof and GET /intern/requirements/{requirement}/proof return an authenticated attachment for an owned current proof.
- Submission accepts only JPEG, PNG, WebP, or PDF content up to 10,240 KB and a comment up to 5,000 characters.
- Submission is allowed only from not_submitted or changes_requested.

- [x] **Step 1: Add failing intern feature tests.** Verify own item lists, denial of another internship's items, student/company role denial, password-setup middleware, accepted image/PDF, unsupported type and oversize rejection, missing file rejection, optional comment persistence, and denial of pending/approved resubmission.

~~~php
Storage::fake('local');

$this->internRequest($intern)
    ->post("/api/intern/tasks/{$task->id}/submission", [
        'proof' => UploadedFile::fake()->image('proof.png')->size(128),
        'comment' => 'The report is attached.',
    ])
    ->assertOk()
    ->assertJsonPath('data.submission_status', 'pending_review')
    ->assertJsonPath('data.proof_available', true)
    ->assertJsonMissingPath('data.proof_path');
~~~

- [x] **Step 2: Run InternWorkItemTest and confirm the new endpoints are not found.**

Run from backend: php artisan test --compact tests/Feature/InternWorkItemTest.php

- [x] **Step 3: Add intern Form Requests.** Use file content/MIME validation for JPEG, PNG, WebP, and PDF, max 10,240 KB; require proof; allow comment to be null and limit it to 5,000 characters.

~~~php
return [
    'proof' => [
        'required',
        'file',
        'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
        'max:10240',
    ],
    'comment' => ['nullable', 'string', 'max:5000'],
];
~~~

- [x] **Step 4: Add scoped read and submission actions.** Use the authenticated intern's current internship and require company_id to be present. Resolve the task/requirement through that internship. Reject invalid submission states with 409. Store the upload on the local private disk using a generated path, update the current submission metadata, set a first-submitted task to in_progress, leave requirements incomplete, clear prior review metadata, and remove the superseded file after the replacement is persisted. On a failed write, remove the new unreferenced file and preserve the previous record.

~~~php
$task = $request->user()->currentInternship()->firstOrFail()
    ->tasks()
    ->whereKey($taskId)
    ->firstOrFail();

abort_unless(
    in_array($task->submission_status, ['not_submitted', 'changes_requested'], true),
    409,
);
~~~

- [x] **Step 5: Add proof download actions.** Read only through Storage::disk('local'); return an attachment with a generated safe filename and detected content type. Do not return a Storage URL or path in JSON.

- [x] **Step 6: Add replacement cleanup assertions.** Submit one proof, change the item to changes_requested as test setup, submit another proof, then assert the new path exists, the old path does not, submission_status is pending_review, and review metadata is cleared. Verify an invalid resubmission leaves the existing proof intact.

- [x] **Step 7: Run InternWorkItemTest and company work-item tests.**

Run from backend: php artisan test --compact tests/Feature/InternWorkItemTest.php tests/Feature/CompanyWorkItemTest.php

### Task 4: Add company review and company proof access

**Files:**

- Modify: backend/app/Http/Controllers/Company/TaskController.php
- Modify: backend/app/Http/Controllers/Company/RequirementController.php
- Create: backend/app/Http/Requests/ReviewCompanyWorkItemRequest.php
- Modify: backend/routes/api.php
- Test: backend/tests/Feature/CompanyWorkItemTest.php

**Interfaces:**

- POST /company/interns/{intern}/tasks/{task}/approve and /request-changes
- POST /company/interns/{intern}/requirements/{requirement}/approve and /request-changes
- GET proof routes use the same nested company/intern/item scope.
- Review action accepts an optional comment for approval and requires a non-empty comment up to 5,000 characters for request-changes.
- Reviews are valid only from pending_review. Approval sets submission_status approved, item status completed, completed_at, reviewed_at, and review_comment. Requesting changes sets changes_requested and reviewed_at while preserving the open task/requirement status.

- [x] **Step 1: Add failing review and company-file tests.** Cover approval and completion timestamps for each item type, request-changes validation requiring a comment, resubmission availability only after changes requested, denial of review while not pending or already approved, cross-company and cross-intern proof denial, and wrong-role denial.

- [x] **Step 2: Run CompanyWorkItemTest and confirm review routes are not found.**

Run from backend: php artisan test --compact tests/Feature/CompanyWorkItemTest.php

- [x] **Step 3: Add review validation and state transitions.** Lock the item row during the state check and update. Store review timestamps and comments. For approved tasks and requirements set the existing status to completed and completed_at. For request-changes leave the existing item status open.

~~~php
$task->update([
    'submission_status' => 'approved',
    'status' => 'completed',
    'completed_at' => now(),
    'review_comment' => $request->validated('comment'),
    'reviewed_at' => now(),
]);
~~~

The request-changes branch sets submission_status to changes_requested, stores its required comment and reviewed_at, and leaves status and completed_at unchanged.

- [x] **Step 4: Add scoped company proof retrieval.** Resolve the company's selected intern, then resolve the item through that exact internship before reading its proof path. A mismatched item/intern pair returns 404.

- [x] **Step 5: Run company/intern work-item tests and personal TaskTest/RequirementTest regressions.**

Run from backend: php artisan test --compact tests/Feature/CompanyWorkItemTest.php tests/Feature/InternWorkItemTest.php tests/Feature/TaskTest.php tests/Feature/RequirementTest.php

### Task 5: Add service methods, routes, and role-specific navigation

**Files:**

- Modify: frontend/src/services/ojt.js
- Modify: frontend/src/services/ojt.test.js
- Modify: frontend/src/router/index.js
- Modify: frontend/src/router/index.test.js
- Modify: frontend/src/App.vue
- Modify: frontend/src/App.test.js
- Modify: frontend/src/components/MobileNavigationDrawer.vue
- Modify: frontend/src/components/MobileNavigationDrawer.test.js

**Interfaces:**

- Company service methods list/create tasks and requirements per intern and approve/request changes.
- Intern service methods list tasks/requirements and submit proof as FormData.
- Proof download methods request blobs through the shared API client; no hard-coded API base URL or public storage URL.
- Company routes are /company/tasks and /company/requirements; intern routes are /intern/tasks and /intern/requirements with existing role guards.

- [x] **Step 1: Add failing service and router/navigation tests.** Assert company/intern endpoints, multipart FormData fields, blob responseType for proof downloads, role restrictions, redirects for wrong roles, and role-specific desktop/mobile links. Keep existing student route/navigation assertions.

- [x] **Step 2: Run the focused frontend service/router/App/drawer tests and confirm expected missing methods/routes fail.**

Run from frontend: npm.cmd run test -- --run src/services/ojt.test.js src/router/index.test.js src/App.test.js src/components/MobileNavigationDrawer.test.js

- [x] **Step 3: Add service methods through the existing Axios client.** Send FormData without manually setting a multipart Content-Type header. Request proof as a blob and return the blob to the view for a user-triggered download.

~~~js
export const submitInternTaskProof = (id, formData) =>
  unwrap(api.post('/intern/tasks/' + id + '/submission', formData))

export const downloadInternTaskProof = (id) =>
  api.get('/intern/tasks/' + id + '/proof', { responseType: 'blob' })
~~~

- [x] **Step 4: Add routes with roles.** Add company-only task and requirements routes and intern-only counterparts. Preserve existing defaultWorkspacePath behavior and leave student route definitions unchanged.

- [x] **Step 5: Add company and intern links to desktop navigation, mobile bottom navigation, and drawer.** Keep personal student links and labels unchanged; stay within the existing five-destination mobile limit.

- [x] **Step 6: Run the focused frontend service/router/App/drawer tests.**

### Task 6: Build company task and requirement pages

**Files:**

- Create: frontend/src/views/company/CompanyTasksView.vue
- Create: frontend/src/views/company/CompanyTasksView.test.js
- Create: frontend/src/views/company/CompanyRequirementsView.vue
- Create: frontend/src/views/company/CompanyRequirementsView.test.js

**Interfaces:**

- Each page loads company interns, allows selecting one intern, creates its item type, lists the selected intern's items, downloads pending proof, and exposes approve/request-changes actions only for pending submissions.
- Request-changes requires a comment in the review form. Approval comment is optional.
- Assignment fields are title, optional description, optional due date; requirement creation also exposes the existing is_required flag defaulting to true. No edit/delete controls are rendered.

- [x] **Step 1: Add failing view tests.** Cover intern loading and selection, empty intern/item states, create validation and success, list loading/error/retry, proof download errors, pending review controls, required request-change comment, approval comment being optional, and absence of assignment edit/delete controls.

- [x] **Step 2: Run the new company view tests and confirm they fail because the views are missing.**

Run from frontend: npm.cmd run test -- --run src/views/company/CompanyTasksView.test.js src/views/company/CompanyRequirementsView.test.js

- [x] **Step 3: Implement the two pages with existing PageHeader, DatePickerField, pagination/status styles, form validation feedback, and the approved role-specific services.** Keep task and requirement forms distinct and avoid adding a generic work-item framework. Render approve/request-changes controls only when submission_status is pending_review.

~~~vue
<button
  v-if="item.submission_status === 'pending_review'"
  type="button"
  @click="openReview(item)"
>
  Review proof
</button>
~~~

- [x] **Step 4: Verify keyboard labels, visible focus, mobile stacking, file-download busy/error feedback, and status text not relying on color alone.**

- [x] **Step 5: Run the new company view tests and service/router/navigation regressions.**

### Task 7: Build intern task and requirement pages

**Files:**

- Create: frontend/src/views/intern/InternTasksView.vue
- Create: frontend/src/views/intern/InternTasksView.test.js
- Create: frontend/src/views/intern/InternRequirementsView.vue
- Create: frontend/src/views/intern/InternRequirementsView.test.js

**Interfaces:**

- Each page lists the authenticated intern's items and shows current item status, submission status, submitted file availability, submission comment, and review comment.
- A file input accepts one JPEG, PNG, WebP, or PDF up to 10 MB. A comment is optional and limited to 5,000 characters.
- Submit/resubmit controls appear only for not_submitted or changes_requested. Pending items show waiting-for-review state; approved items show completion and no submission controls.
- Proof downloads use the authenticated blob service method.

- [x] **Step 1: Add failing view tests.** Cover loading, empty, recoverable error/retry, allowed and disabled submission states, file/comment validation, submit success, change-request visibility, resubmission after a change request, approved completion, and proof download errors.

- [x] **Step 2: Run the new intern view tests and confirm they fail because the views are missing.**

Run from frontend: npm.cmd run test -- --run src/views/intern/InternTasksView.test.js src/views/intern/InternRequirementsView.test.js

- [x] **Step 3: Implement separate task and requirement views with the existing page headers, form controls, and feedback patterns.** Do not reuse or modify student TasksView or RequirementsView.

~~~js
const formData = new FormData()
formData.append('proof', selectedFile.value)
if (comment.value.trim()) formData.append('comment', comment.value.trim())
await submitInternTaskProof(task.id, formData)
~~~

- [x] **Step 4: Verify accessible file labels, keyboard operation, focus/error feedback, and responsive layouts.**

- [x] **Step 5: Run the new intern view tests and personal student Tasks/Requirements tests.**

### Task 8: Update product documentation and verify the full slice

**Files:**

- Modify: PROJECT_CONTEXT.md
- Modify: DESIGN.md
- Modify: AGENTS.md
- Modify: README.md

- [x] **Step 1: Update documentation with company assignment creation, intern private proof submission, review transitions, private local storage, and the fact that personal student task/requirement behavior remains unchanged.** Remove this slice from the current out-of-scope lists, but keep submission history, assignment editing/deletion, notifications, and public URLs out of scope.

- [x] **Step 2: Run the complete Laravel feature suite.**

Run from backend: php artisan test --compact

- [x] **Step 3: Run all frontend tests and the production build.**

Run from frontend: npm.cmd run test -- --run

Run from frontend: npm.cmd run build

- [x] **Step 4: Run browser verification with a disposable test database.** Register/sign in a first company, create an intern, assign a task and a requirement, sign in as the intern, upload proof, request changes from the company, resubmit, approve, and confirm the current proof and completion state. Register/sign in a second company with its own intern and verify it cannot list or download the first company's items. Sign in as a self-registered student and confirm its existing Tasks and Requirements pages still work. Use a small and mobile viewport.

- [x] **Step 5: Review the final diff against the Slice 3 spec and AGENTS.md.** Confirm only the two submission/review state transitions exist, proof storage remains private, no edit/delete/history/notifications or new dependency was added, student APIs were not changed, and no commit was made.

- [x] **Step 6: Run git diff --check and report exact commands/results and any MySQL/browser limitations.** Do not run migrations against the user's local database or commit changes.
