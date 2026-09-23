# OJT Progress Tracker UI Refactor Design

## Authority and scope

> ARCHIVED / HISTORICAL: The current OJT-specific design authority is the root
> DESIGN.md; this document preserves the earlier UI refactor decisions.

This spec translates the approved UI-refactor brief into implementation decisions.

- `cal/DESIGN.md` is the visual reference: light neutral SaaS surfaces, black primary actions, restrained borders/radii, generous spacing, and semantic status colors.
- The pasted UI refactor brief is the functional UI requirement.
- Root `DESIGN.md`, `PROJECT_CONTEXT.md`, and `AGENTS.md` remain project constraints when the reference document is silent or conflicts.
- Existing Phase 0 and Phase 2–3 business rules, routes, authorization, and database behavior remain unchanged except for the pagination response required below.

The deliverable is a responsive Vue UI refactor for login, the student Work Hours/Tasks/Requirements pages, and the coordinator Work Log Review/Tasks/Requirements pages. Phase 4 assistant, notification, analytics, reports, and AI functionality are explicitly out of scope.

## Experience direction

Use a calm, professional student productivity interface:

- white and very light gray canvas/surfaces;
- near-black text and black primary actions;
- subtle gray borders, moderate radius, and light shadows only where they improve grouping;
- semantic green, amber, and red status treatments paired with readable text;
- no gradients, glassmorphism, oversized authenticated-page hero typography, decorative graphs, or scheduling-specific Cal branding;
- one centered content width of about 1100–1280px with consistent page-header spacing;
- responsive table-to-card transformations without forced horizontal scrolling.

Keep the existing dependency set. Use CSS and native browser behavior before adding packages.

## Application shell

Authenticated pages use a white 64px top shell with the product name, role-appropriate navigation, current user identity/role, and a Logout button. Student navigation is Work Hours, Tasks, Requirements. Coordinator navigation is Work Log Review, Tasks, Requirements. The active destination is conveyed by text weight/background treatment rather than an arbitrary blue accent.

On small screens the shell remains usable with a compact menu or wrapped navigation; the page content does not require horizontal scrolling. Public Home/About links are not shown inside the focused login experience.

Every page uses a reusable page header with title, concise description, and an optional right-aligned primary action. Empty, loading, error, and success states are deliberate and compact.

## Shared UI contracts

Create only the reusable pieces needed by multiple views:

- `AppModal.vue`: semantic dialog with `aria-modal`, labelled title, optional description, backdrop click behavior, Escape close, focus return, responsive width, internal scrolling, and footer slots. It must not close while a submitted action is loading.
- `ConfirmDialog.vue`: `AppModal` wrapper with explicit Cancel and action buttons, destructive styling support, and loading label support.
- `PageHeader.vue`: title/description/action slot.
- `PaginationControls.vue`: current range/total summary and previous, next, and page controls driven by Laravel paginator metadata; hidden/disabled when there is only one page.
- existing `StatusBadge`, `ProgressBar`, and `EmptyState`: restyled and reused rather than duplicated.
- one formatter module exposing `formatDate`, `formatTime`, and `formatDuration`.

Use normal buttons with visible labels for primary actions. Put secondary row actions in an accessible overflow menu where the brief calls for it. Do not use `window.confirm()`.

## Login and session actions

Login has a focused auth layout with product identity, welcome copy, email/password labels, a password visibility toggle, inline validation, API-unavailable feedback, and loading/disabled submit state. It does not include role selection.

Logout opens a confirmation dialog with Cancel and Sign out. Cancel leaves the current route and session unchanged. Sign out disables duplicate actions, shows “Signing out…”, calls the existing auth store, and returns to `/login`.

## Student Work Hours

The page displays approved-only progress as `approved hours / total hours`, percentage, progress bar, and remaining hours. The Add/Edit form is modal-only. Failed validation keeps the dialog open and displays field errors.

Logs use status filters All, Draft, Submitted, Approved, Rejected. List data is server-paginated with default page size 10. The frontend sends `page` and optional allowlisted `status`, resets to page 1 when filters change, and renders Laravel metadata rather than slicing a full client array.

Desktop uses a compact table/list; mobile uses stacked cards. Dates, times, and durations are human-readable. Draft rows expose Submit plus an overflow menu for Edit/Delete. Rejected rows expose Resubmit plus overflow actions. Submitted/Approved rows expose View. Submit and Delete use confirmation dialogs. Rejected remarks are visible but visually secondary.

## Coordinator Work Log Review

Review remains limited to submitted logs returned by the existing coordinator endpoint and is server-paginated with default page size 10. Rows show Student, Date, Schedule, Rendered, Status, and a View action. There are no direct row-level approve/reject mutations.

View opens a detail dialog. Approve is available only from that dialog and requires confirmation. Reject opens a remarks dialog with required remarks validation. Loading labels are explicit (`Approving…`, `Rejecting…`), and duplicate submissions are disabled.

## Tasks and Requirements

Student pages become compact responsive lists/cards with consistent StatusBadge usage, human-readable dates, and the existing Start/Submit/Continue Revision and requirement-submit workflows.

Coordinator task assignment and requirement creation/editing use modal forms rather than permanently visible forms. Submitted task/requirement review uses detail dialogs. Request Revision and Reject use required-remarks dialogs; Complete and Approve retain the existing business rules. No task/requirement pagination is added unless implementation reveals an existing endpoint contract that already requires it.

## API contract changes

Only work-log index endpoints change shape:

- `GET /api/student/internship/work-logs?page=<n>&status=<draft|submitted|approved|rejected>` returns the standard Laravel resource paginator with `data`, `links`, and `meta`; default page size is 10.
- `GET /api/coordinator/work-logs?status=submitted&page=<n>` returns the same paginator shape with default page size 10.

The existing resource item fields and mutation routes are unchanged. Invalid/unknown status filters are ignored or rejected consistently with current Laravel validation conventions; the frontend only sends the allowlisted values.

## Quality bar

Add or update focused tests for shared modal/dialog behavior, login/logout confirmation, Work Hours modal validation/submit/delete/filter/pagination/formatting, coordinator review view/approve/reject, and the existing task/requirement action flows. Add backend feature assertions for both paginator contracts. Extend browser smoke coverage for student and coordinator flows and one mobile viewport. Run backend tests, frontend tests, production build, smoke, and `git diff --check` before completion.

Acceptance requires keyboard-accessible dialogs, visible labels, associated errors, text-plus-color status communication, no duplicate submits, no forced mobile horizontal scroll, and the exact final scope statement: “UI/UX refactor completed without implementing Phase 4 functionality.”
# Historical document — superseded by the current student-only implementation documented in `PROJECT_CONTEXT.md` and `README.md`.
