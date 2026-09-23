# Phase 5 QA Log

Verified on 2026-09-23 against the local Laravel API, Vue frontend, seeded
canonical demo data, and the documented Playwright smoke flow.

## Workflow checks

| Test case | Expected result | Actual result | Status |
| --- | --- | --- | --- |
| Registration | Two-step account and OJT setup creates one authenticated student | Duplicate email stays on Step 1; valid registration enters Overview; backend creation is atomic | PASS |
| Login/session | Valid login enters Overview and refresh keeps the authenticated shell | Browser smoke logs in, reloads Overview, and confirms public links are absent | PASS |
| Logout | Confirmation is required and session is invalidated | Account menu sign-out confirmation returns to Login | PASS |
| Overview | Progress, pace, attention, and readiness are backend-derived | Overview tests and browser smoke render the assistant sections without work-log controls | PASS |
| Work Hours | Logs calculate server-side, count immediately, remain editable, and paginate | CRUD, duplicate/date/time rules, pagination, and browser add flow pass | PASS |
| Tasks | To Do → In Progress → Completed workflow is student-only | CRUD, transition rules, server filters, pagination, and browser flow pass | PASS |
| Requirements | Incomplete ↔ Completed workflow preserves required/optional meaning | CRUD, completion/reopen protection, overdue filter, pagination, and browser flow pass | PASS |
| Profile | Schedule/profile/password changes validate safely | Current-password requirement, password policy, date fields, and browser checks pass | PASS |
| Theme | Light/dark mode is readable and persists | Public and authenticated theme toggles persist through refresh and login | PASS |

## Responsive and accessibility checks

- Browser smoke checks Home and About at 375px, 390px, and 430px, and
  authenticated Overview, Work Hours, Tasks, and Requirements at all three
  mobile widths with no horizontal overflow.
- The same smoke checks run the public Home and authenticated shell at 1024px,
  1366px, 1440px, and 1920px; registration and the full CRUD path run at
  390px without clipped controls.
- Account dropdown bounds stay inside the 375px viewport and Escape closes it.
- Shared dialogs have labelled `dialog` semantics, focus restoration, Escape
  close behavior, and a Tab/Shift+Tab focus trap.
- Date calendars use labelled controls, live month headings, disabled invalid
  dates, and modal navigation.
- Reduced-motion behavior is covered by unit tests and browser smoke checks.

## Data and security checks

- All 33 Laravel routes were inspected; no coordinator or reviewer routes are
  present.
- All migrations report `Ran`.
- Student ownership and cross-student isolation pass backend feature tests.
- The canonical seeder is idempotent and creates one representative student
  scenario without broad deletion of unknown users.
- User-facing errors use safe messages; no SQL, stack trace, or exception dump
  is exposed in the smoke flow.

## Automated evidence

| Check | Result |
| --- | --- |
| `cd backend; php artisan test` | 90 tests, 397 assertions passed |
| `cd frontend; npm.cmd run test -- --run` | 26 test files, 101 tests passed |
| `cd frontend; npm.cmd run build` | PASS; Vite production build generated |
| `python scripts/phase0_smoke.py` with both local servers | PASS; registration, refresh, mobile, dates, CRUD, relogin, theme, and demo login |
| `cd backend; php artisan route:list` | 33 routes; no coordinator routes |
| `cd backend; php artisan migrate:status` | All migrations `Ran` |
| `git diff --check` | PASS (exit 0; Git emitted harmless LF→CRLF working-copy warnings) |

## Known test-environment notes

Vitest emits jsdom `window.scrollTo()` not-implemented notices from router
scroll behavior. They do not fail tests and do not occur in the browser smoke.
