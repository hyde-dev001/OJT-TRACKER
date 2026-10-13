# Company-Managed OJT Notifications Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` and implement this plan sequentially in the current workspace. The project uses one main agent; do not delegate or commit changes without explicit approval.
>
> **Scope note:** This is the original company/intern notification plan. The approved personal-student notification extension and its implementation steps are documented in `docs/superpowers/plans/2026-10-14-personal-student-notifications.md`; follow the updated notification specification for current product behavior.

**Goal:** Add reliable, database-backed in-app notifications for company account holders and company-managed interns, with direct links to the affected assignment.

**Architecture:** Use Laravel's standard database notification table with a nullable unique deduplication key. A small notification writer creates safe event payloads inside the same database transactions as assignment, submission, and review changes; one scheduled command handles due-date reminders. A role-gated Vue bell uses the existing Axios client and role-specific task/requirement pages; personal student pages and flows remain unchanged.

**Tech Stack:** Laravel 12, Sanctum, Eloquent, MySQL/MariaDB and SQLite tests; Vue 3, Vue Router, Axios, Tailwind, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-13-company-intern-notifications-design.md`

## Global Constraints

- Only company account holders and company-managed interns receive these notifications; personal student workflows remain unchanged.
- Use only in-app notifications; do not add email, push, polling, sockets, new roles, or dependencies.
- Generate recipients and destinations on the backend; keep notification reads scoped to the authenticated user.
- Enforce deduplication with a database unique constraint and insert-or-ignore writes.
- Persist assignment, proof-submission, and review notifications in the same database transaction as the successful state change.
- Use `Asia/Manila`; scan daily at 9:00 a.m., catch up one overdue alert per actionable past-due item, and avoid stale due-today alerts.
- Preserve existing proof storage, task/requirement states, personal account behavior, and working-tree changes. Do not commit.

## Review Focus

- A forged notification or item ID must never cross user, company, or internship boundaries; cover owner and foreign-account requests in the backend feature tests.
- A repeated or concurrent event attempt must not create duplicates or fail the underlying action; cover unique-key conflict behavior and the same-day scheduler/assignment race path.
- A failed proof save or rolled-back state transition must not leave a notification; cover invalid submissions, invalid reviews, and transaction rollback.
- Missed scheduler runs and same-day assignments must produce the appropriate single reminder; cover due-today, overdue catch-up, pending-review, approved, changes-requested, and no-due-date items.
- A notification load/read failure or item outside the loaded page must remain recoverable and navigable; cover bell states and exact-item navigation across pagination.

## File Map

- Create `backend/database/migrations/*create_notifications_table.php` with Laravel database-notification columns plus a nullable `char(64)` unique `deduplication_key`.
- Create `backend/app/Services/CompanyInternNotifications.php` to build recipient-scoped SHA-256 keys, safe event data, fixed internal destinations, and insert-or-ignore notification rows.
- Create `backend/app/Http/Controllers/NotificationController.php` for paginated list/unread count, mark-one-read, and mark-all-read endpoints.
- Create `backend/app/Console/Commands/SendCompanyWorkItemDeadlineNotifications.php` and register its daily schedule in `backend/bootstrap/app.php`.
- Modify `backend/routes/api.php`, `backend/app/Http/Controllers/Company/TaskController.php`, `backend/app/Http/Controllers/Company/RequirementController.php`, `backend/app/Http/Controllers/Intern/TaskController.php`, `backend/app/Http/Controllers/Intern/RequirementController.php`, and `backend/app/Services/WorkItemProofs.php` to route, emit, and authorize events in existing transactions.
- Modify `backend/composer.json` so the existing local `dev` command also runs Laravel's scheduler worker.
- Create `backend/tests/Feature/CompanyInternNotificationTest.php` for event, deduplication, deadline, list/read, rollback, and ownership coverage.
- Create `frontend/src/components/NotificationBell.vue` and its Vitest file; modify `frontend/src/App.vue` and `frontend/src/App.test.js` to show the bell only for company and intern accounts.
- Modify `frontend/src/services/ojt.js` and its test for notification and item-detail API calls; modify the four company/intern task and requirement views and their tests for exact-item selection/highlighting across pagination.
- Update `PROJECT_CONTEXT.md` and `DESIGN.md` to record the approved notification slice and remove conflicting notification-out-of-scope language.

---

### Task 1: Persist and expose authenticated notifications

**Files:**
- Create the notifications migration, notification writer, controller, and `backend/tests/Feature/CompanyInternNotificationTest.php`.
- Modify `backend/routes/api.php`.

**Interfaces:**
- `CompanyInternNotifications::assignmentCreated(Task|Requirement $item, User $intern): void`
- `CompanyInternNotifications::proofSubmitted(Task|Requirement $item, User $companyHolder): void`
- `CompanyInternNotifications::reviewed(Task|Requirement $item, User $intern, string $decision): void`
- `CompanyInternNotifications::deadline(Task|Requirement $item, User $intern, string $reminderType): void`
- `GET /api/notifications` returns paginated `data`, `meta`, `links`, and `unread_count` for the authenticated recipient.
- `PATCH /api/notifications/{notification}/read` marks only the authenticated user's row read; `POST /api/notifications/read-all` marks only that user's unread rows read.

- [x] **Step 1: Add failing feature tests** for an authenticated recipient seeing only their notifications, unread count and pagination, mark-one/read-all ownership, forbidden personal-student access, and duplicate deduplication keys.
- [x] **Step 2: Run the focused test and confirm it fails** because the migration, endpoints, and writer do not exist.
- [x] **Step 3: Add the migration and minimal writer/controller.** Use Laravel's UUID notification ID, morph columns, JSON data, `read_at`, timestamps, and nullable unique `deduplication_key`. Generate the key from recipient morph type/ID plus event identity, then use `DB::table('notifications')->insertOrIgnore(...)`; do not expose the key or proof paths in API data.
- [x] **Step 4: Add auth-protected routes.** Register notification endpoints once in a shared group guarded by `account-role:company|intern` and `intern.password-changed`; this avoids duplicate root routes shadowing each other. Resolve notification IDs only through `$request->user()->notifications()`; return not found for another user's ID.
- [x] **Step 5: Run `php artisan test --filter=CompanyInternNotificationTest`** from `backend/` and confirm the tests pass.

### Task 2: Emit notifications from successful assignment, submission, and review events

**Files:**
- Modify the four company/intern task and requirement controllers, `backend/app/Services/WorkItemProofs.php`, and `backend/tests/Feature/CompanyInternNotificationTest.php`.

**Interfaces:**
- The notification writer methods from Task 1 receive the saved item and server-derived recipient.
- Extend `WorkItemProofs::replace(HasMany $items, int $itemId, UploadedFile $proof, ?string $comment, ?Closure $afterSaved = null): Model`; call the callback with the saved item inside the existing database transaction.

- [x] **Step 1: Add failing feature tests** for assignment notifications to only the assigned intern; proof-submit/resubmit notifications to only the owning company's account holder; approval/change-request notifications to only the assigned intern; failed state transitions and invalid proof uploads creating no notification; transaction rollback removing both workflow and notification writes.
- [x] **Step 2: Run the focused test and confirm the new event assertions fail.**
- [x] **Step 3: Wrap company assignment creation in `DB::transaction`.** Save the item, call `assignmentCreated`, and, when its due date is today's Manila date, call `deadline(..., 'due_today')` within the same transaction.
- [x] **Step 4: Add event writes to existing transitions.** In company review transactions, notify after the item update; in the proof callback, notify after `WorkItemProofs` updates the item but before its transaction commits. Keep the existing state validation and private file cleanup behavior.
- [x] **Step 5: Verify failed operations leave no event row and successful operations produce exactly one event row.**
- [x] **Step 6: Run `php artisan test --filter=CompanyInternNotificationTest` and `php artisan test --filter='CompanyWorkItemTest|InternWorkItemTest'`** from `backend/`.

### Task 3: Add idempotent Manila-time deadline reminders

**Files:**
- Create `backend/app/Console/Commands/SendCompanyWorkItemDeadlineNotifications.php`.
- Modify `backend/bootstrap/app.php`, `backend/composer.json`, and the notification feature test.

**Interfaces:**
- Artisan command: `company-work-items:send-deadline-notifications`.
- Schedule the command daily at `09:00` with timezone `Asia/Manila` and `withoutOverlapping()`.

- [x] **Step 1: Add failing command tests** using controlled application time for due-today, one-day-overdue, missed-run catch-up, already-deduplicated, pending-review, approved/completed, changes-requested, and no-due-date items.
- [x] **Step 2: Run the command tests and confirm they fail** because the command is not registered.
- [x] **Step 3: Implement the scan using company-owned internships and their intern recipients.** For items due today emit `due_today`; for actionable items with past due dates emit `overdue`. Skip approved/completed and pending-review items. Send each through the writer's same deduplication key used by same-day assignment creation.
- [x] **Step 4: Register the Laravel schedule and add `php artisan schedule:work` to the existing Composer `dev` process list.** Do not add a queue or polling worker for notifications.
- [x] **Step 5: Run `php artisan test --filter=CompanyInternNotificationTest`** and verify repeat runs produce no duplicates.

### Task 4: Add the role-specific notification bell and recoverable read actions

**Files:**
- Create `frontend/src/components/NotificationBell.vue` and `frontend/src/components/NotificationBell.test.js`.
- Modify `frontend/src/App.vue`, `frontend/src/App.test.js`, `frontend/src/services/ojt.js`, and `frontend/src/services/ojt.test.js`.

**Interfaces:**
- Add service functions `listNotifications({ page })`, `markNotificationRead(id)`, and `markAllNotificationsRead()` using the central `api` client.
- Render the bell in the authenticated header only when `user.role` is `company` or `intern`.

- [x] **Step 1: Add failing component/App tests** for company/intern visibility, student absence, unread count, loading, empty, paginated list, load retry, mark-one success/failure, mark-all success/failure, and navigation after a read failure.
- [x] **Step 2: Run the focused Vitest files and confirm the assertions fail.**
- [x] **Step 3: Implement the API helpers and bell list.** Keep last successful unread count on load failure; do not show a false empty list. Update local read state only after server success. On mark-one failure, show a recoverable error and still follow the notification destination.
- [x] **Step 4: Add the bell to desktop and mobile header controls** without adding student notification UI or polling.
- [x] **Step 5: Run the focused tests and `npm run build`** from `frontend/`.

### Task 5: Resolve notification destinations to exact authorized items

**Files:**
- Modify `backend/routes/api.php`, company/intern task and requirement controllers, and `backend/tests/Feature/CompanyInternNotificationTest.php`.
- Modify `frontend/src/services/ojt.js`, its tests, all four company/intern task and requirement views, and their existing view tests.

**Interfaces:**
- Add scoped reads `GET /api/company/interns/{intern}/tasks/{task}`, `GET /api/company/interns/{intern}/requirements/{requirement}`, `GET /api/intern/tasks/{task}`, and `GET /api/intern/requirements/{requirement}`.
- Add matching service helpers `getCompanyInternTask(internId, taskId)`, `getCompanyInternRequirement(internId, requirementId)`, `getInternTask(taskId)`, and `getInternRequirement(requirementId)`.
- Notification data supplies only fixed internal destinations, for example `/company/tasks?intern_id=42&task_id=81` and `/intern/requirements?requirement_id=24`.

- [x] **Step 1: Add backend tests** proving each role can read its own exact item and cannot read another intern's or another company's item; responses omit `proof_path`.
- [x] **Step 2: Add frontend tests** for selecting the company intern from query data, fetching an item absent from the current page, displaying/highlighting it, preserving proof controls, and showing a recoverable missing-target message.
- [x] **Step 3: Implement detail reads through existing internship-scoped relationships.** Use `TaskResource` and `RequirementResource`; do not create a public or unscoped lookup.
- [x] **Step 4: Handle the destination query in the four existing views.** Fetch the exact item even if absent from the current paginated collection, present it as a temporary highlighted target without changing page totals, and use the existing route hash scroll behavior where an item card is rendered.
- [x] **Step 5: Run the focused backend and frontend feature/view tests.**

### Task 6: Align project docs and run regression verification

**Files:**
- Modify `PROJECT_CONTEXT.md` and `DESIGN.md`.
- No unrelated README, database, personal-student, or dependency changes.

- [x] **Step 1: Update project scope and navigation documentation** to add this approved in-app notification slice, its event/deadline rules, role limits, and local scheduler requirement; remove contradictory statements that notifications are out of scope.
- [x] **Step 2: Run backend regression tests** with `php artisan test` from `backend/`.
- [x] **Step 3: Run frontend tests and production build** with `npm run test -- --run` and `npm run build` from `frontend/`.
- [x] **Step 4: Run `git diff --check`** at the repository root and review the diff for personal workflow changes, private data leakage, or unrelated files.

## Spec Coverage Check

- Assignment, proof submission/resubmission, approval, and change-request recipients and transactional persistence: Tasks 1–2.
- Same-day due-today notification and database-enforced deduplication: Tasks 1–3.
- Due-today, overdue, catch-up, actionable-state filtering, Manila time, overlap protection, and local scheduler: Task 3.
- Authenticated list, unread count, read-one/read-all, pagination, isolation, and error states: Tasks 1 and 4.
- Exact contextual navigation and item-detail authorization across pagination: Task 5.
- Personal student flow, mobile visibility, regression checks, and documentation: Tasks 4 and 6.

## Execution Notes

- No new package or queue dependency is required.
- A database unique index is the concurrency boundary; tests should at least prove repeated inserts for the same recipient/event are ignored. SQLite feature tests do not simulate multi-process races, so the MySQL/MariaDB unique constraint remains the production guarantee.
- The scheduler must be running for scheduled reminders. Assignment-day reminders are emitted during assignment creation and do not depend on scheduler timing.
- Do not commit or push without explicit user approval.
