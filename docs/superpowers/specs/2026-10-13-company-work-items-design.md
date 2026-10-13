# Company Tasks, Requirements, and Proof Review

- **Status:** Approved for implementation
- **Date:** 2026-10-13
- **Scope:** Third slice of the company-managed OJT workflow

## Purpose

Let a company account holder assign tasks and requirements to company-managed interns. An intern submits one image or PDF as proof, with an optional comment. The company account holder reviews the submission, approves it, or requests changes. Approval completes the item; a change request leaves it open for resubmission.

The personal student task and requirement tracker remains independent and keeps its current behavior.

## Agreed product rules

- Reuse the existing tasks and requirements tables. A work item belongs to an internship, and a company-managed internship already belongs to a company.
- Do not accept company, internship, or creator ownership from the request body. Derive ownership from the authenticated company account or intern's current internship.
- Company account holders select one of their interns, create tasks and requirements, and review their submissions.
- Company account holders cannot edit or delete an assigned item in this slice.
- Interns can view their own assigned items and submit one image or PDF, up to 10 MB, with an optional comment.
- An intern cannot submit while an item is awaiting review or after approval. A resubmission is allowed after a company requests changes.
- A resubmission replaces and removes the previous proof file. Submission history is not stored.
- The company account holder can approve a pending submission or request changes. A change request requires a comment; an approval comment is optional.
- Approval marks the task or requirement complete. Requesting changes leaves it open and allows another submission.
- Proof files are stored on Laravel's private local disk and are served only through authenticated, ownership-checked endpoints.
- Personal student task and requirement endpoints, status transitions, pages, and completion calculations remain unchanged.
- Do not add additional company roles, notifications, audit/history records, public file URLs, dependencies, or other workflows.

## Data model and states

Keep the existing task and requirement records attached to their existing internship. Add the following submission metadata to both tables:

| Field | Type and behavior |
| --- | --- |
| submission_status | String; defaults to not_submitted |
| proof_path | Nullable private-disk relative path; never serialized to clients |
| submission_comment | Nullable text supplied by the intern |
| review_comment | Nullable text from the company account holder |
| submitted_at | Nullable timestamp for the current proof |
| reviewed_at | Nullable timestamp for the latest decision |

The current proof path and comments represent only the latest submission. The original filename is not stored. Laravel's existing local disk points at storage/app/private; no public symlink or direct storage URL is used.

### Submission and review transitions

The submission state is separate from the existing item status:

| Action | Submission state | Task status | Requirement status |
| --- | --- | --- | --- |
| Company creates item | not_submitted | to_do | incomplete |
| Intern submits proof | pending_review | in_progress | incomplete |
| Company requests changes | changes_requested | in_progress | incomplete |
| Intern resubmits | pending_review | in_progress | incomplete |
| Company approves | approved | completed | completed |

- A submission is accepted only in not_submitted or changes_requested.
- A new submission clears the previous review comment and review timestamp, records the new submission time, and replaces the old proof.
- A review action is accepted only while submission_status is pending_review.
- Requesting changes stores a required review comment and review time. The item remains open.
- Approval stores an optional review comment and review time, sets the existing item status to completed, and records completed_at.
- Approved items are terminal in this slice. No reopen or later resubmission action is added.
- Task and requirement fields continue to use their existing meanings. Company-created requirements may set the existing is_required flag, defaulting to true.

## Workflows

### Company account holder creates assignments

1. The account holder opens the Tasks or Requirements page and selects an intern belonging to their company.
2. The account holder enters a title and optional description and due date. Requirement creation also accepts the existing required flag, defaulting to true.
3. The backend resolves that intern's company-owned internship, validates the due date against its inclusive placement dates, and creates the item with server-assigned ownership and initial states.
4. The account holder sees the selected intern's assignments and their submission/review states.

Assignment due dates are optional. When provided, they must be on or after the internship start date and on or before its target end date. A past date within the placement period remains valid.

### Intern submits proof

1. The intern opens Tasks or Requirements and views assignments for their own company-managed internship.
2. The intern selects an image or PDF and may add a comment, then submits.
3. The backend verifies the role, completed first-sign-in password setup, current internship ownership, item ownership, allowed state, file content type, and size.
4. The item becomes pending_review. The task becomes in_progress; the requirement remains incomplete.
5. If the company requests changes, the intern sees the company comment and can replace the proof and optional comment. The previous file is deleted after replacement succeeds.

Accepted proof types are JPEG, PNG, WebP, and PDF. The maximum file size is 10 MB (10,240 KB).

### Company account holder reviews proof

1. The account holder opens a selected intern's submitted item and downloads its proof through the authenticated company route.
2. The account holder approves it, optionally adding a comment, or requests changes with a required comment.
3. Approval marks the item complete. A change request leaves it open and shows the comment to the intern.

No notifications are sent; both parties see the current state in their respective pages.

## Authorization and file protection

- Company endpoints require the authenticated company role. Company ownership is derived from the account holder's company relation.
- A company item query must be scoped through the selected intern's internship, require the intern role, and require that internship's company to match the authenticated account holder's company.
- Intern endpoints require the intern role and completed password setup. They always use the authenticated intern's own current company-managed internship.
- Company account holders can create and review items only for their own interns. Interns can read and submit only their own assigned items.
- A proof download uses the same company/internship or intern/current-internship scope as the item operation. The item ID alone never grants file access.
- Student accounts cannot use company or intern routes. Company and intern accounts cannot use student item mutation routes.
- File paths and original client filenames are not returned in JSON. Files are never exposed through the public disk or a public URL.
- Uploads must pass server-side MIME/content validation and the 10 MB limit. Invalid types, sizes, states, or fields return field-level validation errors without internal exception details.
- Cross-company or cross-internship item IDs return not found. Wrong-role requests are forbidden.
- On replacement, store the new proof privately, persist its path and current submission data, then remove the previous private file. If storing or persisting the new proof fails, retain the previous proof and remove the unreferenced new file. If storage cleanup itself fails, log the server-side error and do not expose either path to the client.

## API boundaries

Company endpoints:

- GET and POST company interns/{intern}/tasks
- GET and POST company interns/{intern}/requirements
- POST company interns/{intern}/tasks/{task}/approve
- POST company interns/{intern}/tasks/{task}/request-changes
- GET company interns/{intern}/tasks/{task}/proof
- Corresponding approve, request-changes, and proof endpoints for requirements

Intern endpoints:

- GET intern/tasks and GET intern/requirements
- POST intern/tasks/{task}/submission and POST intern/requirements/{requirement}/submission
- GET intern/tasks/{task}/proof and GET intern/requirements/{requirement}/proof

Company creation requests accept item fields only; the backend supplies internship ownership and initial status. Intern submission requests use multipart form data with proof and optional comment. Review requests accept an optional comment for approval and a required comment for request-changes. No endpoint accepts a status, completion time, reviewer, company ID, or proof path from the client.

Proof endpoints return a file download with a server-generated safe filename and content type. They do not return storage paths or persistent public URLs.

## Scope boundaries

This slice includes:

- Company task and requirement creation for a selected owned intern.
- Intern read-only assignment lists and private proof submission/resubmission.
- Company proof review, approval, change requests, and authorized proof downloads.
- Backend validation and company/internship ownership checks.
- Backend, frontend, and browser regression coverage that preserves the current personal student flows.

This slice does not include:

- Assignment editing or deletion.
- Submission or review history, multiple files per item, or public proof URLs.
- Notifications, email, additional company roles, or company verification.
- Changes to personal student task/requirement behavior or completion rules.
- Official certification of company or school completion.

## Acceptance criteria

1. A company account holder can create a task or requirement only for an intern and internship belonging to that company.
2. Company-created items have the agreed initial item and submission states and appear only in the selected intern's company-managed workspace.
3. Company and intern list endpoints return only their authorized internship's items.
4. An intern can submit one valid proof file with an optional comment for an unsubmitted item or an item with changes requested.
5. The backend rejects unsupported or spoofed file types, files larger than 10 MB, missing proof files, comments over 5,000 characters, and submissions in pending-review or approved states.
6. A resubmission replaces the prior proof file, clears the old review comment, and becomes pending review; submission history is not retained.
7. A company account holder can download proof only for an item belonging to that company's selected intern.
8. An intern can download only proof belonging to their own current company-managed internship.
9. Requesting changes requires a comment and keeps the item open. The intern can view the comment and resubmit.
10. Approval completes the task or requirement and prevents further submissions or review actions.
11. Forged company, internship, item, status, completion, or file-path values cannot bypass ownership or workflow checks.
12. Existing personal student task and requirement create, list, edit, delete, status, filters, and completion behavior remains unchanged.
13. No file path or public file URL appears in API responses, and proof files are stored on the private local disk.

## Verification plan

Backend feature tests cover creation/list scoping, cross-company and wrong-role access, due-date validation, item states, approved and change-request transitions, file MIME/size validation, resubmission cleanup, private proof downloads, and preservation of the existing TaskTest and RequirementTest suites.

Frontend tests cover company intern selection, assignment forms, status display, review actions and comments, intern submission and resubmission, private download actions, loading/empty/error states, role routing, and navigation. The existing student Tasks and Requirements tests remain regression coverage.

Browser verification covers company assignment creation, intern proof submission, a change request and resubmission, approval, proof access by the authorized company and intern, denial of unrelated accounts, and continued use of the personal student task and requirement pages.

## Known risks

- Replaced proof files are removed from the private disk after the new proof is persisted. Filesystem cleanup errors must be logged; an inaccessible private orphan may need operator cleanup if the disk reports a deletion failure.
- This slice stores only the current proof. There is no recovery path for a superseded file after a successful resubmission.
- These work items and review states are for project monitoring and do not certify official school or company completion.
