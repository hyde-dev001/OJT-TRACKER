# Registration Flow Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Split student registration into local-state Account Setup and OJT Setup steps, then atomically create the authenticated student and internship with required hours, start date, and target end date.

**Architecture:** Keep one POST /api/register request and the existing Sanctum session flow. The Vue page holds both steps in local reactive state and submits only after Step 2 passes client-side checks; Laravel remains authoritative and creates both records inside the existing database transaction. The existing internships.end_date column and resource are reused.

**Tech Stack:** Vue 3, Vue Router, Pinia, Axios, Tailwind CSS, Laravel 12, Eloquent, PHPUnit, Vitest, Playwright.

**Spec:** The registration-flow refactor specification supplied with this request.

## Global Constraints

- Do not implement Phase 4, analytics, notifications, reports, or new roles.
- Keep Tasks, Requirements, and existing Work Hours calculations unchanged except for direct internship-date compatibility.
- Account Setup contains only name, email, password, and confirmation.
- OJT Setup requires Required OJT Hours, OJT Start Date, and Target End Date.
- Past start dates are valid; target end date must be after start date.
- The client sends hours; Laravel converts hours to integer minutes.
- Do not trust a client-supplied required_minutes.
- Preserve Step 1 values when navigating Back.
- Use the existing password policy and show/hide behavior without weakening validation.

## Review Focus

- A same-day or earlier target end date must never create an account.
- A past OJT start date must remain accepted.
- Duplicate email errors must return the student to Account Setup with entered values intact.
- A failure after user creation must roll back the user and internship together.
- Work Hours must expose the persisted period and constrain its date input without relying on HTML limits as backend validation.

---

### Task 1: Lock down registration contract and atomic backend creation

**Files:**
- Modify: backend/tests/Feature/AuthTest.php
- Modify: backend/app/Http/Requests/RegisterRequest.php
- Modify: backend/app/Http/Controllers/AuthController.php
- Modify: backend/database/seeders/DatabaseSeeder.php
- Modify: backend/database/factories/InternshipFactory.php

**Interfaces:**
- Consumes: POST /api/register with name, email, password, password_confirmation, required_hours, start_date, and end_date.
- Produces: authenticated user response and an internship containing required_minutes, start_date, end_date, and active status.

- [ ] Step 1: Write failing backend tests for end-date persistence, hours-to-minutes conversion, past start dates, same/before date rejection, non-positive hours, duplicate email, invalid password, and rollback when internship creation throws.
- [ ] Step 2: Run php artisan test tests/Feature/AuthTest.php and confirm the new expectations fail because the request currently lacks end_date validation and persistence.
- [ ] Step 3: Add end_date validation using required, date_format:Y-m-d, and after:start_date, with the specified clear message for invalid ranges and field-specific messages for missing setup values.
- [ ] Step 4: Persist end_date in AuthController::register() while keeping required_minutes derived from validated required_hours inside the existing DB::transaction().
- [ ] Step 5: Update seeded/factory internships with a valid future end date so local demos and tests represent the new onboarding contract without changing existing feature behavior.
- [ ] Step 6: Run the focused AuthTest again and confirm it passes, including the rollback assertion.

### Task 2: Implement the two-step registration UI

**Files:**
- Modify: frontend/src/views/RegisterView.vue
- Modify: frontend/src/views/RegisterView.test.js

**Interfaces:**
- Consumes: the existing auth store register(details) method and centralized API error handling.
- Produces: one final registration payload containing both account and internship fields; redirects to /student/work-hours after the existing authenticated response.

- [ ] Step 1: Write failing Vue tests for initial Step 1, blocked Continue with invalid Account Setup, Step 1 to Step 2, Back retention, both password Show/Hide controls, Step 2 fields, immediate invalid date-range feedback, final combined payload, and backend 422 field display.
- [ ] Step 2: Run the focused RegisterView tests and confirm they fail because the current page renders one combined form and has no target end date or second step.
- [ ] Step 3: Add local step state and split the template into Account Setup and OJT Setup forms with clear Step 1 of 2 and Step 2 of 2 headings, account-only and internship-only fields, Continue, Back, and Start Tracking actions.
- [ ] Step 4: Preserve and validate state with local reactive form data, client-side password confirmation/range checks, an end-date minimum of start date plus one day, and backend errors mapped to the visible step.
- [ ] Step 5: Add Show/Hide to both password fields with keyboard-accessible buttons and keep the existing live password requirements attached to the password field.
- [ ] Step 6: Submit required_hours as hours and include end_date; do not add temporary registration state to Pinia or create a second API client.
- [ ] Step 7: Run focused RegisterView tests and confirm the full two-step behavior passes.

### Task 3: Surface the persisted OJT period in Work Hours

**Files:**
- Modify: backend/app/Http/Requests/StoreWorkLogRequest.php
- Modify: backend/tests/Feature/WorkLogTest.php
- Modify: frontend/src/views/student/WorkHoursView.vue
- Modify: frontend/src/views/student/WorkHoursView.test.js

**Interfaces:**
- Consumes: InternshipResource fields start_date and end_date.
- Produces: server-enforced work-date bounds plus a visible OJT period and input bounds using min = start_date and max = min(today, end_date).

- [ ] Step 1: Write failing backend and Work Hours tests for rejecting out-of-period work dates, rendering the persisted period, and applying the date input bounds.
- [ ] Step 2: Run the focused WorkLogTest and WorkHoursView tests and confirm the new expectations fail.
- [ ] Step 3: Add server-side period validation and the smallest computed frontend date-bound helpers; do not change rendered-duration calculation, save, edit, delete, or pagination behavior.
- [ ] Step 4: Run focused WorkLogTest and WorkHoursView tests and confirm they pass.

### Task 4: Extend browser smoke and durable documentation

**Files:**
- Modify: scripts/phase0_smoke.py
- Modify: PROJECT_CONTEXT.md
- Modify: README.md

- [ ] Step 1: Extend browser smoke to register a unique student through Account Setup and OJT Setup, verify Back retention, verify invalid end-date blocking, verify the authenticated redirect, and assert Work Hours shows hours/start/end.
- [ ] Step 2: Document the separate onboarding steps and required internship setup fields in the project context and setup workflow documentation.
- [ ] Step 3: Run the browser smoke with both local servers available and record whether the complete flow passes; do not claim browser verification if a server/browser is unavailable.

### Task 5: Full verification and scope review

- [ ] Step 1: Run php artisan test.
- [ ] Step 2: Run npm.cmd test -- --run.
- [ ] Step 3: Run npm.cmd run build.
- [ ] Step 4: Run git diff --check and review the diff for Phase 4 or unrelated Work Hours, Tasks, or Requirements changes.
- [ ] Step 5: Report exact results, changed files, and any browser verification limitation.
