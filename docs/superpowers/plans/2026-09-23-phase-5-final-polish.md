# Phase 5 Final Polish, QA, Demo Data & Defense Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stabilize the existing student-only OJT tracker, finish the already-approved list pagination/filter request, and leave a verified, documented build suitable for a classroom defense.

**Architecture:** Preserve the current Vue 3 + Laravel architecture, backend-authoritative rules, shared pagination component, existing modal/theme primitives, and one canonical demo student. Add server-side pagination/filter parameters to the existing Tasks and Requirements list endpoints; make only surgical UI, documentation, and smoke-test changes where the audit finds a concrete issue.

**Tech Stack:** Vue 3, Vite, Tailwind CSS, Vue Router, Pinia, Axios, Laravel 12, Sanctum, Eloquent, PHPUnit, Vitest, Vue Test Utils, Playwright smoke.

**Spec:** User-provided Phase 5 brief at `C:\Users\slyha\.codex\attachments\0be38216-e500-46b1-9e15-002c2821da5d\pasted-text.txt`, plus `docs/superpowers/specs/2026-09-23-phase-4-smart-assistant-design.md`.

## Global Constraints

- Keep the product a student-only personal OJT tracker; do not add coordinator, admin, reporting, notification, AI, certification, or external-integration scope.
- Preserve backend-authoritative duration, pace, readiness, validation, and ownership rules.
- Use the existing `PaginationControls`, modal components, theme system, and design tokens; add no dependency.
- Overdue requirements mean incomplete requirements with a due date before today in `Asia/Manila`.
- Keep the canonical demo account `student@example.com` / `OjtTracker!2026` and preserve seeder idempotence.
- Do not commit, reset, push, or rewrite history; AGENTS.md requires explicit authorization for those actions.

## Review Focus

- A filtered Tasks or Requirements page must paginate the filtered dataset, not only the records already loaded in the browser; backend feature tests pin this.
- Moving to a page that disappears after a deletion must recover to the last valid page; frontend tests pin this for both list pages.
- A completed requirement with a past due date must not appear in the Overdue filter; backend and frontend tests pin this.
- Public pages and authenticated pages must retain readable light/dark states and no horizontal overflow at mobile widths; browser smoke assertions pin this.
- Documentation and demo credentials must describe the current Phase 4/5 product without claiming official OJT certification; a final grep/QA review pins this.

### Task 1: Add server-backed pagination and Requirements filters

**Files:**
- Modify: `backend/app/Http/Controllers/Student/TaskController.php`
- Modify: `backend/app/Http/Controllers/Student/RequirementController.php`
- Modify: `backend/tests/Feature/TaskTest.php`
- Modify: `backend/tests/Feature/RequirementTest.php`

**Interfaces:**
- `GET /api/student/tasks?page=<positive integer>&filter=all|to_do|in_progress|completed` returns the existing resource shape with Laravel `data`, `meta`, and `links`.
- `GET /api/student/requirements?page=<positive integer>&filter=all|incomplete|completed|overdue` returns the existing resource shape with Laravel `data`, `meta`, and `links`.
- `overdue` applies `status != completed` and `due_date < today()`; null due dates are excluded.

- [x] **Step 1: Write failing backend feature tests** for Task pagination/status filtering and Requirements pagination/all four filters, including completed-past-due exclusion and invalid filter rejection.
- [x] **Step 2: Run the focused backend tests and confirm the new assertions fail because the endpoints still return unpaginated collections.**
- [x] **Step 3: Implement validated query parameters and `paginate(10)->withQueryString()` using the existing internship scoping and ordering.**
- [x] **Step 4: Run the focused backend tests and confirm pagination metadata and filters pass.**

### Task 2: Connect Tasks and Requirements UI to pagination/filter state

**Files:**
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/views/student/TasksView.vue`
- Modify: `frontend/src/views/student/RequirementsView.vue`
- Modify: `frontend/src/views/student/TasksView.test.js`
- Modify: `frontend/src/views/student/RequirementsView.test.js`
- Reuse: `frontend/src/components/PaginationControls.vue`

**Interfaces:**
- `listStudentTasks({ page, filter })` and `listStudentRequirements({ page, filter })` return `{ items, meta, links }`.
- Changing a status/filter control resets page to 1 and requests that filter from the server.
- Pagination controls preserve the active filter and emit the next page.

- [x] **Step 1: Write failing Vue/service tests** for page requests, server-side task filters, Requirements Overdue filter, pagination rendering, filter reset, no-match empty copy, and last-page recovery after deletion.
- [x] **Step 2: Run focused frontend tests and confirm they fail against the current array-only Task/Requirement services.**
- [x] **Step 3: Implement the shared paginated response handling, filter controls, page recovery, and accessible responsive controls without changing the existing CRUD/modals.**
- [x] **Step 4: Run focused frontend tests and confirm all list behavior passes.**

### Task 3: Perform surgical Phase 5 UI/accessibility/theme corrections

**Files:**
- Modify only the concrete issue locations found during the audit, expected candidates: `frontend/src/assets/main.css`, `frontend/src/components/AppModal.vue`, `frontend/src/components/ConfirmDialog.vue`, `frontend/src/components/StudentAccountMenu.vue`, primary Vue views, and `scripts/phase0_smoke.py`.
- Test: the nearest existing component/view test for every behavior change.

- [x] **Step 1: Audit all primary views, shared controls, loading/empty/error/success states, keyboard paths, reduced motion, light/dark tokens, and responsive overflow against DESIGN.md and the Phase 5 brief.**
- [x] **Step 2: Add failing tests only for confirmed regressions, prioritizing modal focus/Escape behavior, filter/pagination accessibility, dark-mode contrast, and stale loading copy.**
- [x] **Step 3: Apply the smallest fixes; do not redesign already-working screens or add a UI library.**
- [x] **Step 4: Run the focused tests and verify no coordinator/reviewer runtime copy reappears.**

### Task 4: Finalize demo data, documentation, and defense materials

**Files:**
- Review/modify: `backend/database/seeders/DatabaseSeeder.php` only if the audit finds a real idempotence or Phase 4 demonstration gap.
- Modify: `README.md`, `PROJECT_CONTEXT.md`, `DESIGN.md` where current Phase 5 status or workflow text is stale.
- Create: `docs/QA/phase-5-qa.md` with only verified test evidence.
- Create: `docs/DEFENSE_GUIDE.md` covering system flow, feature problem/solution/backend/frontend/database/validation/test explanations, innovation statement, Overview-vs-Work-Hours distinction, limitations, common Q&A, and the 5–7 minute demo sequence.

- [x] **Step 1: Add tests or inspection checks for canonical seeder idempotence, representative progress/pace/attention/readiness data, and safe demo credentials before changing seed/docs.**
- [x] **Step 2: Update only stale current documentation; leave archived historical plans/specs unchanged.**
- [x] **Step 3: Write QA and defense documents from actual verified behavior, explicitly stating that the tracker does not certify official OJT completion.**
- [x] **Step 4: Run documentation/runtime searches for stale Phase 4-future, coordinator, reviewer, approval, or official-certification claims in active docs/runtime code and resolve genuine findings.**

### Task 5: Run final regression and browser verification

**Files:**
- Modify: `scripts/phase0_smoke.py` only for verified missing coverage.
- Modify: `docs/QA/phase-5-qa.md` with exact fresh results.

- [x] **Step 1: Run `php artisan test`, `npm.cmd run test -- --run`, `npm.cmd run build`, `php artisan route:list`, `php artisan migrate:status`, and `git diff --check`; record exact counts and harmless line-ending warnings.**
- [x] **Step 2: Run the documented Playwright smoke with backend and frontend servers, including desktop/mobile, theme persistence, authenticated refresh, account menu, CRUD flow, date boundaries, pagination/filter controls, and no horizontal overflow.**
- [x] **Step 3: Perform a final read-only review against the Phase 5 brief, AGENTS.md, PROJECT_CONTEXT.md, DESIGN.md, and this plan; do not start another phase or commit.**
- [x] **Step 4: Update the QA log and final report with remaining known limitations only; distinguish verified behavior from items not exercised.**
