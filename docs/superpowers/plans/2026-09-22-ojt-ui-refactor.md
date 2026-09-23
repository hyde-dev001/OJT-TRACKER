# OJT Progress Tracker UI Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor all Phase 2–3 student/coordinator screens into a cohesive responsive UI based on `cal/DESIGN.md`, while preserving business behavior and adding real work-log pagination.

**Architecture:** Keep the existing Vue 3 views, Pinia stores, Axios service, and Laravel controllers. Add a small shared UI layer (modal, confirmation, page header, pagination, formatters) and compose it inside the existing views. Change only the two work-log index endpoints to use Laravel pagination; normalize that response in the existing service layer.

**Tech Stack:** Vue 3, Vue Router, Pinia, Axios, Tailwind CSS v4, Vitest, Laravel feature tests, Playwright smoke script.

**Spec:** `docs/superpowers/specs/2026-09-22-ojt-ui-refactor-design.md`

## Global Constraints

- `cal/DESIGN.md` is a visual reference; do not copy scheduling-specific Cal branding or patterns.
- Preserve existing Phase 0 and Phase 2–3 business rules, authorization, routes, and mutation behavior.
- Do not implement Phase 4 assistant, notifications, analytics, reports, or AI functionality.
- Use the existing dependency set; prefer CSS and native browser behavior over new packages.
- Work-log pagination defaults to 10 and must use Laravel paginator metadata; do not fake pagination by slicing a full client response.
- Dialogs must be keyboard accessible, labelled, escapable, responsive, and safe against duplicate submits.

## Review Focus

- A rejected log/task/requirement must retain and display its remarks without allowing a mutation that the existing state machine disallows; covered in the owning view tests.
- A paginator response with zero items, one page, multiple pages, and a page beyond the last page must render stable controls and empty states; covered in pagination/service and Work Hours/review tests.
- Closing a dialog by Escape/backdrop or Cancel must not discard a pending mutation or leave stale edit state; covered in AppModal and view tests.
- A failed submit with field errors must keep the form modal open and preserve entered values; covered in Work Hours and coordinator form tests.
- Mobile list/card layouts must not require horizontal scrolling and must keep the primary action reachable; covered in browser smoke at a mobile viewport.

## Task 1: Shared UI foundation and formatters

**Files:**
- Create: `frontend/src/components/AppModal.vue`
- Create: `frontend/src/components/ConfirmDialog.vue`
- Create: `frontend/src/components/PageHeader.vue`
- Create: `frontend/src/components/PaginationControls.vue`
- Create: `frontend/src/utils/formatters.js`
- Modify: `frontend/src/assets/main.css`
- Modify: `frontend/src/components/StatusBadge.vue`
- Modify: `frontend/src/components/ProgressBar.vue`
- Modify: `frontend/src/components/EmptyState.vue`
- Test: `frontend/src/components/AppModal.test.js`, `frontend/src/components/ConfirmDialog.test.js`, `frontend/src/components/PaginationControls.test.js`, `frontend/src/utils/formatters.test.js`

**Interfaces:**
- Produces `AppModal` props `open`, `title`, optional `description`, `closeOnBackdrop`, `closeOnEscape`, and `busy`; slots `default`, `footer`; emits `close`.
- Produces `ConfirmDialog` props `open`, `title`, `message`, `confirmLabel`, `cancelLabel`, `variant`, and `busy`; emits `cancel` and `confirm`.
- Produces `PaginationControls` props `meta` and emits `change(page)`.
- Produces formatter functions `formatDate(value)`, `formatTime(value)`, and `formatDuration(minutes)`.

- [ ] **Step 1: Write failing tests** for modal semantics/Escape/focus return, confirmation button labels and busy state, pagination page events/hidden single-page state, and formatter examples (`2026-09-04` → `Sep 4, 2026`, `08:00:00` → `8:00 AM`, `510` → `8h 30m`).
- [ ] **Step 2: Run the focused tests** with `npm.cmd run test -- --run src/components/AppModal.test.js src/components/ConfirmDialog.test.js src/components/PaginationControls.test.js src/utils/formatters.test.js`; expect failures because the new modules do not exist.
- [ ] **Step 3: Implement the shared components and design tokens** using semantic HTML (`role="dialog"`, `aria-modal`, labelled title), native keydown listeners, focus restoration, CSS media queries, and no new dependency. Restyle existing shared components to the neutral token palette and keep StatusBadge text-readable.
- [ ] **Step 4: Run the focused tests again** and expect all new tests to pass.
- [ ] **Step 5: Run the existing component tests** with `npm.cmd run test -- --run src/components`; expect the prior component behavior to remain green.

## Task 2: Laravel work-log pagination and frontend service normalization

**Files:**
- Modify: `backend/app/Http/Controllers/Student/WorkLogController.php`
- Modify: `backend/app/Http/Controllers/Coordinator/WorkLogReviewController.php`
- Modify: `frontend/src/services/ojt.js`
- Modify: `backend/tests/Feature/WorkLogTest.php`
- Modify: `frontend/src/services/ojt.test.js` (create if absent)

**Interfaces:**
- Consumes existing `WorkLogResource` fields and mutation routes.
- Produces `listStudentWorkLogs({ page = 1, status = '' })` and `listCoordinatorWorkLogs({ page = 1 })`, each returning `{ items, meta, links }`.
- Backend list endpoints return Laravel resource paginator metadata with default `perPage = 10`.

- [ ] **Step 1: Add failing backend feature assertions** that create 11 logs, request page 1, and assert `data` count 10 plus `meta.current_page`, `meta.per_page`, and `meta.total`; add a coordinator equivalent. Add a status-filter assertion for student logs.
- [ ] **Step 2: Run the focused backend tests** with `php artisan test --filter=WorkLogTest`; expect the new paginator assertions to fail because controllers currently return unpaginated collections.
- [ ] **Step 3: Implement minimal pagination** with `paginate(10)` and an allowlisted student `status` filter; preserve existing scopes/ordering and authorization. Update `ojt.js` with a small paginated-response normalizer that leaves all non-paginated list wrappers unchanged.
- [ ] **Step 4: Add service normalization tests** mocking Axios responses for paginated and non-paginated payloads, then run `npm.cmd run test -- --run src/services/ojt.test.js`; expect PASS.
- [ ] **Step 5: Re-run `php artisan test --filter=WorkLogTest`** and expect all work-log feature tests to pass.

## Task 3: Authenticated shell, login, and logout confirmation

**Files:**
- Modify: `frontend/src/App.vue`
- Modify: `frontend/src/views/LoginView.vue`
- Modify: `frontend/src/views/LoginView.test.js`
- Modify: `frontend/src/stores/auth.test.js`

**Interfaces:**
- Consumes `ConfirmDialog`, `PageHeader` styles, and the existing `authStore` actions/state.
- Produces role-specific active navigation and a confirmation-gated logout flow.

- [ ] **Step 1: Add failing tests** for password visibility toggle, disabled/loading login, authenticated student/coordinator navigation, logout Cancel preserving the route, and logout confirmation invoking the store only after Sign out.
- [ ] **Step 2: Run `npm.cmd run test -- --run src/views/LoginView.test.js src/stores/auth.test.js`; expect the new interaction assertions to fail.
- [ ] **Step 3: Refactor `App.vue` and `LoginView.vue`** to use the compact white shell and focused login layout. Keep logout pending until confirmation, show `Signing out…`, and route to `/login` after success.
- [ ] **Step 4: Re-run the focused tests and then `npm.cmd run test -- --run src/router/index.test.js src/views/LoginView.test.js src/stores/auth.test.js`; expect PASS.

## Task 4: Student Work Hours workflow

**Files:**
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/views/student/WorkHoursView.vue`
- Modify: `frontend/src/views/student/WorkHoursView.test.js`
- Modify: `frontend/src/components/ProgressBar.vue`

**Interfaces:**
- Consumes `AppModal`, `ConfirmDialog`, `PaginationControls`, `StatusBadge`, `PageHeader`, formatter functions, and paginated `listStudentWorkLogs`.
- Produces filter/page state that sends `{ page, status }`, resets page on filter change, and renders `items/meta` without client slicing.

- [ ] **Step 1: Add failing view tests** for Add modal open/close, invalid save keeping the modal open, submit confirmation, delete confirmation, status-filter requests, page-change requests, formatted date/time/duration, approved-only progress, and rejected remarks.
- [ ] **Step 2: Run `npm.cmd run test -- --run src/views/student/WorkHoursView.test.js`; expect the new tests to fail against the always-visible form and immediate `window.confirm` behavior.
- [ ] **Step 3: Refactor the view** into a compact progress header plus filterable table/card list. Move create/edit into one modal, use confirmation dialogs for submit/delete, put secondary actions in an overflow menu or equivalent accessible button group, preserve field errors, and render pagination metadata from the service.
- [ ] **Step 4: Re-run the focused view tests, then all student view tests** with `npm.cmd run test -- --run src/views/student`; expect PASS.

## Task 5: Coordinator Work Log Review workflow

**Files:**
- Modify: `frontend/src/views/coordinator/WorkLogReviewView.vue`
- Modify: `frontend/src/views/coordinator/WorkLogReviewView.test.js`

**Interfaces:**
- Consumes `AppModal`, `ConfirmDialog`, `PaginationControls`, `StatusBadge`, `PageHeader`, formatter functions, and paginated coordinator list data.
- Produces a view-only row action; approve/reject mutations are available only inside the detail dialog.

- [ ] **Step 1: Add failing tests** for View opening complete details, Approve confirmation and loading, Reject remarks required validation, pagination requests, and absence of direct row-level approve/reject buttons.
- [ ] **Step 2: Run `npm.cmd run test -- --run src/views/coordinator/WorkLogReviewView.test.js`; expect the new tests to fail against the current inline action buttons.
- [ ] **Step 3: Implement the detail, approve, and reject dialog flows** with explicit loading labels, required remarks, responsive table/cards, and formatter/pagination reuse.
- [ ] **Step 4: Re-run the focused test and all coordinator view tests** with `npm.cmd run test -- --run src/views/coordinator`; expect PASS.

## Task 6: Student and coordinator Tasks/Requirements refactor

**Files:**
- Modify: `frontend/src/views/student/TasksView.vue`
- Modify: `frontend/src/views/student/RequirementsView.vue`
- Modify: `frontend/src/views/coordinator/TasksView.vue`
- Modify: `frontend/src/views/coordinator/RequirementsView.vue`
- Modify: corresponding four `*.test.js` files

**Interfaces:**
- Consumes shared modal/dialog/status/header/formatter primitives and existing `ojt.js` task/requirement methods.
- Produces compact responsive lists; coordinator create/edit forms are modal-only; submitted/rejected review actions use detail/remarks dialogs.

- [ ] **Step 1: Add failing tests** for coordinator assignment/add modals, modal validation preservation, student action labels, submitted detail/revision or rejection remarks dialogs, and responsive-safe action rendering.
- [ ] **Step 2: Run the four focused view test files** with `npm.cmd run test -- --run src/views/student/TasksView.test.js src/views/student/RequirementsView.test.js src/views/coordinator/TasksView.test.js src/views/coordinator/RequirementsView.test.js`; expect the new modal assertions to fail.
- [ ] **Step 3: Refactor the four views** without changing service calls or allowed statuses. Remove permanently visible coordinator forms, add modal forms, reuse StatusBadge/formatters, and use detail/remarks dialogs for review actions. Keep primary actions visible and secondary actions compact.
- [ ] **Step 4: Re-run all four focused tests and the complete frontend suite** with `npm.cmd run test -- --run`; expect PASS.

## Task 7: Browser smoke, visual QA, and final verification

**Files:**
- Modify: `scripts/phase2_3_smoke.py` (or the current browser smoke script)
- Modify: `README.md` only if the documented local URL is inconsistent with the smoke script

- [ ] **Step 1: Extend smoke coverage** for student login → Work Hours → Add modal → Save → Submit confirmation → Tasks → Requirements → Logout cancel/confirm; coordinator login → review View → approve confirmation/reject → Tasks assign modal → Requirements add modal; include one mobile viewport and assert no horizontal overflow.
- [ ] **Step 2: Run the smoke script** using the project’s existing start commands and record any failure before changing code.
- [ ] **Step 3: Fix only failures found by smoke/visual QA** using the smallest relevant test-first change, then rerun the affected test and smoke path.
- [ ] **Step 4: Run final verification:** `php artisan test`, `npm.cmd run test -- --run`, `npm.cmd run build`, the browser smoke script, and `git diff --check`; read each result before claiming completion.
- [ ] **Step 5: Perform a whole-branch self-review** against the spec, checking all six authenticated pages plus login at desktop and mobile sizes. Record any deferred minor explicitly; do not add Phase 4 work.
# Historical document — superseded by the current student-only implementation documented in `PROJECT_CONTEXT.md` and `README.md`.

