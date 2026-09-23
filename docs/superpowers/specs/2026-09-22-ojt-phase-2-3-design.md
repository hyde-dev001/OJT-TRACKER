# OJT Progress Tracker — Phase 2–3 Design

**Date:** 2026-09-22  
**Status:** Approved for planning  
**Scope:** Minimal authentication and roles, Work-Hour Progress Tracking, Task Monitoring, and Requirement Monitoring

## Goal

> ARCHIVED / HISTORICAL: This document records the earlier Phase 2–3 plan.
> Current product truth is in PROJECT_CONTEXT.md and the root DESIGN.md.

Extend the Phase 0 Vue/Laravel foundation into one authenticated workflow for a student intern and a supporting coordinator. The backend owns identity, authorization, workflow transitions, rendered-minute calculations, and official progress. The frontend presents those results clearly and provides only actions allowed for the current role and record state.

Phase 4’s Progress & Needs-Attention Assistant, completion readiness, notifications, analytics, reports, uploads, and other excluded features remain out of scope.

## Chosen approach

Use Laravel Sanctum’s first-party SPA/session authentication. The Vue app uses the existing Axios client with credentials and CSRF bootstrap, keeps the current user in a small Pinia auth store, and protects navigation with route metadata/guards. Laravel middleware and policies are authoritative; frontend guards are only a usability layer.

This is preferred over browser-stored personal access tokens because it matches the requested first-party SPA flow and avoids exposing long-lived tokens to JavaScript. It is preferred over generic status endpoints because dedicated transition actions make authorization and illegal-state behavior explicit and testable.

## Domain model

### Users

Add a required `role` value to the existing users table: `student` or `coordinator`. Keep the current Laravel password hashing cast. No registration, email verification, password reset, profile management, MFA, or role editor is included.

### Internships

`internships` stores the student’s active OJT assignment:

- `student_id` → users, cascade on student deletion
- `coordinator_id` → users, restrict on coordinator deletion
- `required_minutes` unsigned integer
- `start_date`
- nullable `end_date`
- `status`: `active` or `completed`
- timestamps

Index the ownership/status fields used by scoped queries. Only the assigned coordinator may review an internship; students may access only their own internship.

### Work logs

`work_logs` stores one daily record per internship:

- `internship_id`
- `work_date`
- `time_in`, `time_out`
- `break_minutes`
- authoritative `rendered_minutes`
- nullable `accomplishment_summary`
- `status`: `draft`, `submitted`, `approved`, or `rejected`
- nullable `reviewer_remarks`, `submitted_at`, `reviewed_by`, `reviewed_at`
- timestamps

Add a unique constraint on `(internship_id, work_date)`. Add indexes for internship/status/date and reviewer lookup where useful. The server calculates `rendered_minutes` from the submitted source values; a client value is ignored or rejected and never becomes authoritative.

### Tasks

`tasks` stores coordinator-assigned work:

- `internship_id`
- `assigned_by` → users
- `title`, nullable `description`, nullable `due_date`
- `status`: `to_do`, `in_progress`, `submitted`, `needs_revision`, or `completed`
- nullable `submitted_at`, `completed_at`, `reviewer_remarks`
- timestamps

No percentage field or Kanban-specific model is needed.

### Requirements

`requirements` stores internship deliverables:

- `internship_id`
- `created_by` → users
- `title`, nullable `description`, nullable `due_date`
- `is_required`
- `status`: `not_submitted`, `submitted`, `approved`, or `rejected`
- nullable `student_notes`, `reviewer_remarks`, `submitted_at`, `reviewed_by`, `reviewed_at`
- timestamps

No file upload or document-management fields are included.

## Authorization and workflow invariants

Use authenticated route groups, a small role middleware, and policies/scoped queries. Every student query starts from the authenticated user’s internship. Every coordinator query starts from internships assigned to that coordinator. Route model binding must not bypass those scopes.

### Work logs

- Student creates or edits only a `draft` or `rejected` log belonging to the student’s internship.
- Student may delete only a `draft` or `rejected` log.
- Student submits only a `draft` or `rejected` log.
- Submitted logs are locked until the coordinator approves or rejects them.
- Approved logs are immutable and count toward official progress.
- Rejection requires remarks and leaves the record editable for correction/resubmission.
- Coordinator approves or rejects only submitted logs for supervised internships.
- Rejection does not count toward progress; only approved `rendered_minutes` are summed.

Validate required dates/times, `time_out > time_in`, non-negative break minutes not exceeding the session, and positive calculated duration. Enforce one record per internship/date at both validation and database levels.

### Tasks

- Student may view only own tasks.
- Student transitions: `to_do → in_progress`, `in_progress → submitted`, and `needs_revision → in_progress`.
- Coordinator may create/edit tasks and transition submitted tasks to `completed` or `needs_revision` for supervised internships.
- Students cannot assign or complete tasks.
- Illegal transitions return a clear validation/conflict response.

### Requirements

- Student may view and submit/update notes for own requirements in `not_submitted` or `rejected` states.
- Student may resubmit a rejected requirement.
- Coordinator may create/edit, approve, or reject requirements for supervised internships.
- Approval/rejection uses dedicated actions; rejection requires remarks.
- Approved requirements cannot be modified by students.

## Progress response

Expose one backend-calculated progress object with:

- `approved_minutes` and `approved_hours`
- `required_minutes` and `required_hours`
- `remaining_minutes` and `remaining_hours`, floored at zero
- `percentage`, capped at 100

The calculation is derived only from approved work logs and the internship’s `required_minutes`. Vue formats the returned values but does not reproduce business rules.

## API shape

Use the existing `/api` prefix and JSON responses. Avoid a generic status-update endpoint.

### Authenticated identity

- `POST /api/login`
- `GET /api/user`
- `POST /api/logout`

Sanctum CSRF bootstrap is used by the frontend before login; login errors, unauthenticated requests, forbidden requests, validation errors, missing records, server failures, and network errors are mapped to human-readable UI states.

### Student

- `GET /api/student/internship`
- `GET /api/student/work-logs`
- `POST /api/student/work-logs`
- `GET /api/student/work-logs/{workLog}`
- `PUT /api/student/work-logs/{workLog}`
- `DELETE /api/student/work-logs/{workLog}`
- `POST /api/student/work-logs/{workLog}/submit`
- `GET /api/student/tasks`
- `POST /api/student/tasks/{task}/start`
- `POST /api/student/tasks/{task}/submit`
- `POST /api/student/tasks/{task}/return-to-progress`
- `GET /api/student/requirements`
- `POST /api/student/requirements/{requirement}/submit`

The requirement submission action accepts student notes and supports rejected-item resubmission without a generic update route.

### Coordinator

- `GET /api/coordinator/work-logs`
- `POST /api/coordinator/work-logs/{workLog}/approve`
- `POST /api/coordinator/work-logs/{workLog}/reject`
- `GET /api/coordinator/tasks`
- `POST /api/coordinator/tasks`
- `GET /api/coordinator/tasks/{task}`
- `PUT /api/coordinator/tasks/{task}`
- `POST /api/coordinator/tasks/{task}/complete`
- `POST /api/coordinator/tasks/{task}/request-revision`
- `GET /api/coordinator/requirements`
- `POST /api/coordinator/requirements`
- `GET /api/coordinator/requirements/{requirement}`
- `PUT /api/coordinator/requirements/{requirement}`
- `POST /api/coordinator/requirements/{requirement}/approve`
- `POST /api/coordinator/requirements/{requirement}/reject`

Coordinator list/create forms identify the supervised internship without exposing unrelated student records.

## Frontend structure

Keep the existing Vue 3/JavaScript/Router/Pinia/Axios/Tailwind stack. Add only the smallest reusable pieces with multiple consumers:

- auth store and API error/CSRF handling in existing service boundaries;
- authenticated shell/navigation with role-specific links;
- `LoginView`;
- student Work Hours, Tasks, and Requirements views;
- coordinator Work Log Review, Tasks, and Requirements views;
- `StatusBadge`, `ProgressBar`, and `EmptyState` components;
- local form/list helpers only where reuse is demonstrated.

Pages must show visible labels, field-level validation, loading, empty, success, forbidden/error states, keyboard focus, and responsive mobile layouts. Use semantic status text alongside color. Keep the Phase 0 landing/about pages available only as appropriate for unauthenticated navigation; do not create an empty authenticated dashboard.

## Testing and verification

Backend feature tests cover:

- unauthenticated rejection and login/current-user/logout;
- role checks, cross-student isolation, and coordinator supervision scoping;
- work-log calculation, spoofed rendered-minute protection, invalid time/break values, duplicates, transitions, locks, remarks, and approved-only progress;
- task creation, ownership, legal/illegal transitions, and coordinator review;
- requirement ownership, submission, approval/rejection, self-approval prevention, and approved immutability.

Frontend tests cover behavior rather than Tailwind classes: progress rendering, validation, status labels, empty states, task actions, requirement rejection remarks, and route guards where practical.

Browser smoke covers the end-to-end demo path: student login → draft work log → submit; coordinator login → review → approve; student progress update; student task transition to submitted; and requirement submission.

Final verification commands are `php artisan test`, frontend Vitest, `npm run build`, the browser smoke script, and `git diff --check`.

## Seed/demo constraints

Seed realistic but fictional school-defense data: one coordinator, one student, one active internship with 500 required hours, approved/submitted/rejected/draft work logs, varied task states, and varied requirement states. Credentials are development-only and documented in README without adding secrets to tracked environment files.

## Out of scope and stop condition

Do not implement Phase 4 aggregate needs-attention logic, due-soon assistant behavior, completion readiness, notifications, analytics/reports, certificates, GPS/QR/biometrics, payroll, chat, AI, uploads, or a complex admin portal. Individual overdue/rejected/pending labels are allowed because they belong to the Phase 2–3 records themselves.

Stop after Phase 3 verification and report any unverified checks explicitly.
# Historical document — superseded by the current student-only implementation documented in `PROJECT_CONTEXT.md` and `README.md`.
