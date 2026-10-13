# Company Attendance and Work-Hour Tracking Implementation Plan

> **For agentic workers:** After user approval, execute this plan sequentially with `superpowers:executing-plans`. Use one main agent and do not commit changes unless the user explicitly authorizes a commit.

**Goal:** Add company-managed live attendance, manager-editable work-hour entries, and intern read-only work hours while preserving personal student work logs.

**Architecture:** Reuse `work_logs`, `WorkLog`, `WorkLogResource`, and `Internship::progressSummary()`. Add company-scoped start, end, list, and correction endpoints plus an intern-only list endpoint. Add one company attendance page and one intern read-only Work Hours page using the existing Vue router, Axios client, formatting helpers, and navigation patterns.

**Tech Stack:** Laravel 12, PHP 8.2+, Sanctum, Eloquent, MySQL/MariaDB, Vue 3, JavaScript, Vue Router, Axios, Tailwind CSS, PHPUnit, Vitest, and Vue Test Utils.

**Spec:** `docs/superpowers/specs/2026-10-13-company-attendance-design.md` (approved by the user on 2026-10-13, including manager editing of completed work-hour entries)

## Global Constraints

- Reuse the existing `work_logs` table and `WorkLog` model.
- One session is allowed per intern per workday.
- Starting a session records the server's current date and time in the configured application timezone (`Asia/Manila` by default).
- Ending a session records the server's current time and keeps the session's saved break minutes, which default to zero. The account holder can adjust break minutes through Edit work hours.
- The backend validates time ordering and break duration and calculates rendered minutes. Client-supplied rendered minutes or status are ignored.
- Only completed sessions contribute to progress. The current `Internship::progressSummary()` already sums completed work logs and returns a null percentage when required minutes are unset.
- The company account holder can edit an intern's saved work-hour entries, including completed sessions, by correcting the work date, time-in, time-out, and break minutes.
- Corrections cannot set company, intern, internship, status, or rendered minutes directly. Sessions cannot be deleted in this slice.
- Company routes require the authenticated `company` role and ownership is derived from that account. Intern routes are read-only and use the authenticated intern's own current internship.
- Existing `/student/work-logs` routes and student authorization remain unchanged.
- Do not add multiple sessions per day, overnight sessions, attendance deletion, change-history auditing, automatic reminders, location tracking, proof uploads, company task/requirement assignment, official attendance certification, dependencies, or commits.

## Review Focus

- **Two starts close together:** keep the database unique key as the concurrency guard and map duplicate-key conflicts to a field-level date error; test sequential API duplication and that a duplicate database insert still violates the unique key.
- **Break or time boundary values:** reject time-out at or before time-in and breaks equal to or greater than elapsed time; test zero break and the smallest valid rendered duration.
- **Placement schedule changed after a session:** allow edits that retain an existing date, while requiring a changed date to be within the current valid placement and workday schedule; pin both cases in backend tests.
- **Application timezone crosses midnight:** record local Manila date/time on start and reject a same-day time-out after midnight; test the rejected time-out and correction to valid same-day values.
- **Open session with no required-hours target:** show an in-progress record without rendered minutes or percentage and deny all intern write attempts; cover API and UI behavior.

---

## File Structure

### Backend

- Create `backend/database/migrations/2026_10_13_000002_allow_open_company_work_logs.php` to make `work_logs.time_out` and `work_logs.rendered_minutes` nullable while preserving existing rows and the unique internship/date key.
- Create `backend/app/Http/Controllers/Company/WorkLogController.php` for company-scoped attendance list, start, time-out, and correction actions.
- Create `backend/app/Http/Requests/StoreCompanyWorkLogTimeOutRequest.php` and `backend/app/Http/Requests/UpdateCompanyWorkLogRequest.php` for break and correction validation.
- Create `backend/app/Http/Controllers/Intern/WorkLogController.php` for the intern's paginated read-only log list.
- Modify `backend/app/Http/Resources/WorkLogResource.php` to serialize an open session's time-out and rendered minutes as `null`.
- Modify `backend/routes/api.php` to add company and intern routes under their existing role/password middleware.
- Add `backend/tests/Feature/CompanyWorkLogTest.php` and `backend/tests/Feature/InternWorkLogTest.php`; keep existing `WorkLogTest.php` as the personal-flow regression suite.

### Frontend

- Create `frontend/src/views/company/CompanyAttendanceView.vue` and its test for intern selection, time-in, time-out, and editing open or completed work-hour entries.
- Create `frontend/src/views/intern/InternWorkHoursView.vue` and its test for read-only progress and session history.
- Modify `frontend/src/services/ojt.js` and `frontend/src/services/ojt.test.js` for the new endpoints, using the existing Axios client and `unwrapPage` helper.
- Modify `frontend/src/router/index.js`, `frontend/src/App.vue`, and `frontend/src/components/MobileNavigationDrawer.vue` to add role-scoped company Attendance and intern Work Hours navigation.
- Reuse `PageHeader.vue`, `DatePickerField.vue`, `PaginationControls.vue`, and existing time/date/duration formatting helpers where their current behavior fits.

### Documentation

- Update `PROJECT_CONTEXT.md`, `DESIGN.md`, `AGENTS.md`, and `README.md` to describe company attendance and intern read-only work hours while keeping task assignment, proof, and review outside this slice.

---

### Task 1: Allow open sessions in the existing work-log schema

**Files:**

- Create: `backend/database/migrations/2026_10_13_000002_allow_open_company_work_logs.php`
- Test: `backend/tests/Feature/CompanyWorkLogTest.php`

**Interfaces:**

- The `work_logs` row may have `time_out = null`, `rendered_minutes = null`, and `status = in_progress` while a session is open.
- The existing unique key on `(internship_id, work_date)` remains in place.

- [ ] **Step 1: Add a failing persistence test for an open session.** Create a company-owned internship and attempt to persist an open log. Assert that the retrieved fields are null and its status is `in_progress`.

```php
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
```

In the same test file, add a database-level assertion that two work logs cannot share an internship and work date. Expect Laravel's `UniqueConstraintViolationException` on the second insert; this pins the existing key that protects close-together starts.

- [ ] **Step 2: Run the new test and confirm the current non-null schema rejects it.**

Run from `backend`: `php artisan test --compact tests/Feature/CompanyWorkLogTest.php`

- [ ] **Step 3: Add the schema migration.** Use Laravel `change()` to make `time_out` and `rendered_minutes` nullable. Its rollback changes the fields back to non-null only when no open rows remain. Do not drop or recreate the work-log table or unique key; existing completed records keep their values.

- [ ] **Step 4: Run the new persistence test and `php artisan test --compact tests/Feature/WorkLogTest.php`.** Confirm the open row persists and existing student logs still pass.

---

### Task 2: Add company-scoped attendance actions and editing

**Files:**

- Create: `backend/app/Http/Controllers/Company/WorkLogController.php`
- Create: `backend/app/Http/Requests/StoreCompanyWorkLogTimeOutRequest.php`
- Create: `backend/app/Http/Requests/UpdateCompanyWorkLogRequest.php`
- Modify: `backend/app/Http/Resources/WorkLogResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/CompanyWorkLogTest.php`

**Interfaces:**

- `GET /company/interns/{intern}/work-logs` returns that owned intern's logs, newest date first, paginated by 10.
- `POST /company/interns/{intern}/work-logs/time-in` captures Manila-local current date/time; the request body cannot override either value.
- `POST /company/interns/{intern}/work-logs/time-out` accepts `{ "break_minutes": integer }`, captures current time, and returns the completed `WorkLogResource`.
- `PUT /company/interns/{intern}/work-logs/{workLog}` accepts `{ "work_date": "Y-m-d", "time_in": "H:i", "time_out": "H:i|null", "break_minutes": integer }`; the backend recalculates `rendered_minutes` and sets `in_progress` or `completed` based on `time_out`.
- Company ownership and intern relationship are resolved from the authenticated company's internship relation; IDs in the request body are never used to choose ownership.

- [ ] **Step 1: Add failing company API tests.** Cover server-captured time-in, open response values, duplicate starts, time-out with break calculation, and editing a completed session with recomputed minutes despite forged `rendered_minutes` and `status` fields.

```php
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
```

- [ ] **Step 2: Run `php artisan test --compact tests/Feature/CompanyWorkLogTest.php` from `backend` and confirm the company route and behavior tests fail.**

- [ ] **Step 3: Implement the company controller and requests.** Use the existing `Company\InternController` pattern to derive the authenticated company and scope the intern. On time-in, verify today's placement bounds/workday, create the open row, and convert a duplicate-key conflict into a `work_date` validation error. On time-out, require today's open row, validate `break_minutes`, capture `now()->format('H:i')`, and calculate minutes with `WorkLog::calculateRenderedMinutes()`. On correction, validate date/time/break, permit keeping an existing date after schedule changes, validate any changed date, and recompute status/duration. Never accept `rendered_minutes`, `status`, `internship_id`, or `company_id` from the client.

- [ ] **Step 4: Add tests for break/time boundaries, placement and workday rules, stale or missing open sessions, and ownership.** Verify zero break is accepted, a 60-minute session with a 59-minute break renders one minute, and a break equal to the session is rejected. Verify a corrected completed entry recalculates hours; verify a manager cannot list, time, or edit a different company's intern's logs. Test a session crossing Manila midnight: the normal time-out endpoint rejects the next-day time and correction can save valid same-day values.

- [ ] **Step 5: Run `php artisan test --compact tests/Feature/CompanyWorkLogTest.php tests/Feature/WorkLogTest.php`.** Confirm company actions pass and personal student CRUD/progress behavior remains unchanged.

---

### Task 3: Add intern read-only work-log access

**Files:**

- Create: `backend/app/Http/Controllers/Intern/WorkLogController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/InternWorkLogTest.php`

**Interfaces:**

- `GET /intern/work-logs` lists only the authenticated intern's current internship work logs, paginated by 10 and serialized through `WorkLogResource`.
- The route is inside `account-role:intern` and `intern.password-changed` middleware.
- The endpoint has no create, update, or delete action.

- [ ] **Step 1: Add failing tests for own-log pagination, in-progress serialization, no-target progress, and blocked access before password change.** Also assert that an intern cannot use company time-in, time-out, or correction routes.

```php
$this->internRequest($intern)
    ->getJson('/api/intern/work-logs')
    ->assertOk()
    ->assertJsonPath('data.0.status', 'in_progress')
    ->assertJsonPath('data.0.time_out', null)
    ->assertJsonPath('data.0.rendered_minutes', null);

$this->internRequest($intern)
    ->postJson("/api/company/interns/{$intern->id}/work-logs/time-in")
    ->assertForbidden();
```

- [ ] **Step 2: Run `php artisan test --compact tests/Feature/InternWorkLogTest.php` from `backend`; confirm the route is missing and new access expectations fail.**

- [ ] **Step 3: Add the controller's read-only `index()` action.** Resolve `$request->user()->currentInternship()->firstOrFail()`, order by date and ID descending, paginate by 10, and return `WorkLogResource::collection(...)`.

- [ ] **Step 4: Add assertions that a second intern cannot see the first intern's logs, the personal student cannot use this endpoint, and an intern requiring a password change is blocked.** Confirm `Internship::progressSummary()` excludes `in_progress` and returns a null percentage while required hours are unset.

- [ ] **Step 5: Run `php artisan test --compact tests/Feature/InternWorkLogTest.php tests/Feature/InternAccountSetupTest.php tests/Feature/WorkLogTest.php`.**

---

### Task 4: Add the company attendance page

**Files:**

- Create: `frontend/src/views/company/CompanyAttendanceView.vue`
- Create: `frontend/src/views/company/CompanyAttendanceView.test.js`
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/services/ojt.test.js`
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/App.vue`
- Modify: `frontend/src/components/MobileNavigationDrawer.vue`

**Interfaces:**

- `listCompanyInternWorkLogs(internId, options)` calls the company work-log GET endpoint and returns `{ items, meta, links }` via `unwrapPage()`.
- `startCompanyWorkLog(internId)` posts to the time-in endpoint; `endCompanyWorkLog(internId, payload)` posts `{ break_minutes }`; `updateCompanyWorkLog(internId, workLogId, payload)` sends work date/time/break corrections.
- `/company/attendance` requires authentication and the `company` role. The page loads the existing intern list, lets the account holder select one intern, and shows that intern's sessions.
- The page offers Time in when no session is open today, Time out for today's open session, and Edit work hours for open or completed entries. Time out keeps the saved break value, defaulting to zero; the holder can adjust it in the edit form. It has no delete action.

- [ ] **Step 1: Add failing service tests for list/start/end/update calls and page tests for intern selection, loading/error/empty states, live actions, and editing a completed entry.** Verify edit submissions send times and break minutes but never send rendered minutes/status.

```js
expect(updateCompanyWorkLog).toHaveBeenCalledWith(7, 15, {
  work_date: '2026-10-13',
  time_in: '08:15',
  time_out: '17:00',
  break_minutes: 30,
})
expect(wrapper.text()).toContain('Work hours updated')
```

- [ ] **Step 2: Run `npm.cmd run test -- --run src/services/ojt.test.js src/views/company/CompanyAttendanceView.test.js` from `frontend`; confirm new service and view expectations fail.**

- [ ] **Step 3: Add the service methods and company Attendance view.** Reuse `listCompanyInterns()`, `PageHeader`, date/time/duration helpers, `DatePickerField`, pagination controls, centralized `apiErrorMessage()`, and the existing accessible modal/form styles. On successful actions, reload the selected intern's logs; show field errors from Laravel and recoverable load/action failures.

- [ ] **Step 4: Add the company route and desktop/mobile navigation link.** Keep `/company/interns` intact and keep student navigation unchanged.

- [ ] **Step 5: Run the targeted Vitest command again and confirm manager actions, validation, and refresh states pass.**

---

### Task 5: Add the intern read-only Work Hours page

**Files:**

- Create: `frontend/src/views/intern/InternWorkHoursView.vue`
- Create: `frontend/src/views/intern/InternWorkHoursView.test.js`
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/services/ojt.test.js`
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/App.vue`
- Modify: `frontend/src/components/MobileNavigationDrawer.vue`

**Interfaces:**

- `listInternWorkLogs(options)` calls `GET /intern/work-logs` through `unwrapPage()`.
- `/intern/work-hours` requires authentication and the `intern` role.
- The page loads `getInternInternship()` and `listInternWorkLogs()`, displays the backend progress summary and paginated entries, and has no attendance write controls.

- [ ] **Step 1: Add failing service, route, and view tests.** Verify completed progress, open-session labels with no duration, a missing required-hours target with no percentage, pagination, recoverable errors, and no buttons/forms to start, end, or edit sessions.

```js
expect(wrapper.text()).toContain('In progress')
expect(wrapper.text()).not.toContain('Time in')
expect(wrapper.text()).not.toContain('Edit work hours')
expect(wrapper.text()).toContain('Progress percentage is unavailable until required hours are set')
```

- [ ] **Step 2: Run `npm.cmd run test -- --run src/services/ojt.test.js src/router/index.test.js src/App.test.js src/views/intern/InternWorkHoursView.test.js` from `frontend`; confirm the new intern page and route expectations fail.**

- [ ] **Step 3: Add `listInternWorkLogs()` and the read-only page.** Reuse `getInternInternship()`, pagination and existing date/time/duration formatters. Render `time_out` and `rendered_minutes` as absent for open sessions; show required/remaining/percentage only when present in the backend summary.

- [ ] **Step 4: Add the intern Work Hours route and desktop/mobile navigation link.** Keep the OJT account page and all personal student routes intact.

- [ ] **Step 5: Run the targeted Vitest command again and confirm read-only state, progress, and pagination pass.**

---

### Task 6: Update product documentation and verify the complete slice

**Files:**

- Modify: `PROJECT_CONTEXT.md`
- Modify: `DESIGN.md`
- Modify: `AGENTS.md`
- Modify: `README.md`
- Reference: `docs/superpowers/specs/2026-10-13-company-attendance-design.md`

- [ ] **Step 1: Update product documentation.** State that a company account holder records one daily session, can edit break minutes in the work-hour form, and can edit open or completed work-hour entries; backend duration is recomputed. State that interns can view their hours without attendance controls, the personal student workflow is unchanged, and records are not official attendance certification. Keep task/proof/review flows out of this slice.

- [ ] **Step 2: Run the complete backend and frontend suites.**

Run from `backend`: `php artisan test --compact`

Run from `frontend`: `npm.cmd run test -- --run` and `npm.cmd run build`

Run from the repository root: `git diff --check`

- [ ] **Step 3: Run browser verification against an isolated test database.** Verify company sign-in, start, time-out with break minutes, correction of completed hours, missed time-out correction, intern read-only work hours, role/ownership restrictions, and the existing student Work Hours flow. Do not migrate the user's local database as part of browser verification.

- [ ] **Step 4: Review the final diff against the approved spec.** Confirm no attendance deletion, audit log, multiple daily sessions, overnight sessions, task assignment, proof, review, new dependency, or commit was added.

## Spec Coverage Self-Review

- Product rules for ownership, unique daily sessions, Manila-local time capture, server break/duration calculation, and nullable open sessions: Tasks 1-2.
- Company session listing, start, time-out, correction of open/completed entries, validation, and duplicate handling: Task 2.
- Intern read-only history, completed-hour progress, missing-target behavior, and password setup guard: Task 3.
- Company manager UI and field-level feedback: Task 4.
- Intern read-only UI and progress display: Task 5.
- Personal student work-log regression, docs, full tests, and browser workflow: Tasks 2-6.
- Explicit exclusions and no-commit boundary: Global Constraints and Task 6.

## Execution Boundary

This is an implementation plan only. Do not change application code or run migrations until the user approves this plan. Use sequential native execution in the current session if approved. Do not commit without explicit user approval.
