# Phase 3.75 Hardening Implementation Plan

> **For agentic workers:** Execute this plan inline with `superpowers:executing-plans`; keep the implementation in the current workspace and preserve unrelated user changes.

**Goal:** Harden the student-only OJT tracker for the Phase 4 handoff without implementing any Phase 4 assistant calculations or dashboards.

**Architecture:** Keep Laravel authoritative for dates, schedule data, rendered minutes, status transitions, and ownership. Add only forward migrations, reuse existing Vue views/components and API services, and use the existing deterministic seeder/smoke script for local data hygiene.

**Tech Stack:** Laravel REST API, MySQL/SQLite test migrations, Vue 3 + Vite, Vitest, Playwright smoke script.

**Spec:** `C:\Users\slyha\.codex\attachments\eda8c5c2-e35d-413c-a376-abc4351e4e9e\pasted-text.txt`

## Global Constraints

- Do not implement Needs Attention aggregation, Required Pace, On Track/At Risk, Completion Readiness, notifications, reports, analytics, or AI.
- Target end date is required and must be after OJT start date.
- Store recurring `work_days` as unique ISO weekday values 1–7 and store `expected_daily_minutes` as backend-derived integer minutes.
- Tasks are personal productivity items and do not block completion readiness.
- Phase 4 does not exclude public holidays automatically; pace derives from selected weekdays and the target end date.
- Never fabricate dates or schedules for unknown real records.
- Preserve existing user changes; do not rewrite historical migrations or run destructive database resets.

## Review Focus

- A future-start student must receive an internship response while the UI blocks work-log creation with an explanatory state; forced API writes still fail.
- A completed requirement must reject update/delete, but the existing reopen action must make it editable/deletable again.
- Work-log and requirement legacy defaults must not reappear after a fresh insert or a repeat seed.
- Registration must reject missing/empty/duplicate weekday schedules and invalid expected hours while converting only server-side hours to minutes.
- A one-record final pagination page must fall back to a valid page after deletion.

### Task 1: Schedule, end-date, and status schema hardening

**Files:**
- Create: `backend/database/migrations/2026_09_22_070000_add_ojt_schedule_to_internships.php`
- Create: `backend/database/migrations/2026_09_22_071000_harden_end_date_and_status_defaults.php`
- Modify: `backend/app/Models/Internship.php`, `backend/app/Models/Requirement.php`
- Modify: `backend/app/Http/Resources/InternshipResource.php`
- Modify: `backend/app/Http/Requests/RegisterRequest.php`
- Modify: `backend/app/Http/Controllers/AuthController.php`
- Modify: `backend/app/Http/Controllers/Student/RequirementController.php`
- Test: `backend/tests/Feature/AuthTest.php`, `backend/tests/Feature/InternshipTest.php`, `backend/tests/Feature/RequirementTest.php`, `backend/tests/Feature/WorkLogTest.php`

Implement forward-only migrations. Add nullable schedule columns so unknown legacy rows are not assigned invented values; backfill only the exact demo account (`student@example.com`) with `[1,2,3,4,5]`, 480 minutes, and `2026-12-15`. Before changing `end_date` to non-null, backfill that known demo row, reject any remaining null/invalid unknown rows with a clear migration exception, then change the column. Normalize known legacy work-log/requirement statuses and change defaults to `completed`/`incomplete`.

Use `work_days`/`expected_hours_per_day` in registration validation. Normalize duplicate weekday input before validation, require at least one ISO day, require 0.5–24 expected hours/day, and convert hours to integer minutes in `AuthController`; never accept client `expected_daily_minutes`. Rename active requirement state handling from `not_completed` to `incomplete` while leaving historical migration files unchanged.

Run first: `cd backend; php artisan test --filter=AuthTest` and `php artisan test --filter=RequirementTest` after writing the failing cases for schedule conversion, required end dates, and completed-record protection.

### Task 2: Backend boundary and ownership regressions

**Files:**
- Modify: `backend/app/Http/Controllers/Student/RequirementController.php`, relevant requests/policies/resources
- Modify: `backend/tests/Feature/WorkLogTest.php`, `backend/tests/Feature/RequirementTest.php`, `backend/tests/Feature/AuthorizationTest.php`
- Modify: `backend/database/factories/InternshipFactory.php`, `backend/database/factories/RequirementFactory.php`

Add explicit tests for today accepted, future rejected, before-start/future/after-end update rejection, valid historical update, future-start forced rejection, completed requirement update/delete rejection, reopen-then-edit/delete, and cross-student task/requirement update/delete/status actions. Keep authorization in policies and keep backend date/duration rules authoritative.

### Task 3: Registration and future-start UI behavior

**Files:**
- Modify: `frontend/src/views/RegisterView.vue`, `frontend/src/views/RegisterView.test.js`
- Modify: `frontend/src/views/student/WorkHoursView.vue`, `frontend/src/views/student/WorkHoursView.test.js`
- Modify: `frontend/src/views/student/RequirementsView.vue`, `frontend/src/views/student/RequirementsView.test.js`
- Modify: `frontend/src/components/StatusBadge.vue`

Add compact weekday checkboxes and expected-hours-per-day to registration Step 2, preserve values across steps, submit the exact backend field names, and show local errors. Rename visible requirement active state to `Incomplete` and remove legacy draft/not-submitted labels from active UI.

For a future start date, avoid contradictory date bounds, disable the add-work-log action, show `Your OJT hasn't started yet. Work logging will be available on <date>.`, and guard the modal opener. After deletion, reload the current page and move to the last valid page if the current page disappeared. Add tests for the future-start state and a 21-log page-3 deletion.

### Task 4: Idempotent demo data and browser smoke isolation

**Files:**
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Modify: `scripts/phase0_smoke.py`

Clean only generated accounts matching `browser-registration-*@example.com`; preserve unknown accounts. Seed one canonical `student@example.com` account with password `OjtTracker!2026`, 500 required hours, 2026-09-01 through 2026-12-15, Monday–Friday, and 8 expected hours/day. Seed a small realistic set of completed logs, mixed personal task states, and required/optional requirements. Make repeat seeding idempotent.

Update browser smoke to register, create data, log out, log back in with the same generated credentials, verify persistence, check a mobile viewport, and clean generated data through the deterministic seeder cleanup.

### Task 5: Durable documentation and design reference separation

**Files:**
- Create: `docs/design-reference/CAL_DESIGN.md` as an exact copy of the existing raw Cal reference
- Replace: `DESIGN.md` with OJT-specific authoritative design guidance
- Modify: `PROJECT_CONTEXT.md`, `AGENTS.md`, `README.md`
- Mark relevant older Phase 2–3 specs/plans as historical without rewriting their content
- Delete only verified-unused starter assets `frontend/src/assets/vite.svg`, `frontend/src/assets/vue.svg`, `frontend/src/assets/hero.png`

Document required end date, recurring schedule, expected hours/day, holiday behavior, task non-blocking behavior, the personal-tracker limitation, strong demo credentials, and the Phase 4 boundary. Keep the root design OJT-specific and retain the Cal material only as a reference.

### Task 6: Whole-project verification

Run and read the complete output of:

```text
cd backend; php artisan test
cd frontend; npm.cmd run test -- --run
cd frontend; npm.cmd run build
cd backend; php artisan route:list
cd backend; php artisan migrate:status
git diff --check
```

Run the updated browser smoke with the local Laravel/Vite servers and inspect final schema/data/statuses. Do not claim Phase 3.75 readiness unless the checks support it, and explicitly state that Phase 4 was not implemented.
