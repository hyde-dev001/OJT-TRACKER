# Phase 4 — Smart OJT Progress & Completion Assistant

**Date:** 2026-09-23  
**Status:** Implemented and refined  
**Project:** OJT Progress Tracker

## Intent

Give the student one reliable overview of OJT progress, required pace, items
that need attention, and completion readiness. The assistant is deterministic
application logic, not an AI assistant: the backend calculates the facts and
the frontend presents them in plain language.

The pasted Phase 4 brief is the feature specification. The project
`PROJECT_CONTEXT.md` and root `DESIGN.md` remain the product and visual
constraints. `cal/DESIGN.md` and the copied design reference are visual input,
not permission to add coordinator, scheduling, or analytics features.

## User outcome and acceptance criteria

After signing in, a student lands on an Overview page that answers, in this
order:

1. How many OJT hours are rendered, required, and remaining?
2. What daily pace is required, and is the student on track?
3. What tasks or requirements need attention now?
4. Is the student ready for completion, and if not, what blocks readiness?

The feature is accepted when:

- every displayed progress, pace, status, attention, and readiness value comes
  from the authenticated student's backend response;
- the endpoint cannot read another student's internship by passing an ID;
- schedule calculations use the configured inclusive OJT period and ISO
  weekdays (`1` Monday through `7` Sunday);
- attention ordering and visibility are deterministic and capped at five
  visible items;
- required requirements, but never tasks or optional requirements, block
  readiness;
- login and registration enter the Overview page while protected deep links
  remain usable;
- the page has loading, recoverable error, empty, responsive, and dark-mode
  states;
- backend, frontend, and browser checks cover the workflow and existing
  Work Hours, Tasks, and Requirements flows remain green.

## Scope

### Included

- A student-scoped `GET /api/student/overview` endpoint.
- One backend service that calculates progress, pace, status, attention, and
  completion readiness.
- An authenticated `/student/overview` route and Overview view.
- Overview navigation and post-authentication redirects.
- Reusable existing progress/status UI where it fits, with only focused new
  presentation markup.
- Tests, browser smoke coverage, and durable documentation updates.

### Explicitly excluded

- Notifications, email, SMS, push alerts, or background reminders.
- AI, chat, machine learning, probabilistic predictions, or external model
  calls.
- Holiday APIs, calendar synchronization, time-clock integration, reports,
  exports, coordinator/admin workflows, multi-student analytics, or Phase 5
  features.
- An arbitrary combined score for work hours and requirements.
- Changing the existing Work Hours, Tasks, or Requirements business rules
  beyond the data needed to display the overview.

## Existing integration points

The implementation follows the current Vue 3 + Vite, Vue Router, Pinia,
Axios, Tailwind, Laravel, and MySQL architecture.

- `Internship::progressSummary()` already sums only completed work-log
  `rendered_minutes`, caps percentage at 100, and calculates remaining
  minutes. The Phase 4 service will reuse that source rather than duplicate
  work-hour aggregation.
- `Internship` already stores `required_minutes`, `start_date`, `end_date`,
  `work_days`, and `expected_daily_minutes`.
- `Task` and `Requirement` already expose due dates and completion statuses.
- `config/app.php` and the environment use `Asia/Manila`; date boundaries use
  that application timezone.
- `ProgressBar`, `StatusBadge`, `PageHeader`, `EmptyState`, and the existing
  API unwrap convention are reusable.
- The authenticated shell currently owns student navigation and intentionally
  renders the brand as a non-link. That behavior is preserved.

No new package or schema migration is expected. Existing simplified-schema
  migrations already provide the fields needed by the service, including
  `requirements.completed_at`.

## Backend design

### Endpoint and authorization

Add `GET /api/student/overview` inside the existing `auth:sanctum` and
`student` route groups. The controller obtains the current internship only
through the authenticated user relationship:

```php
$internship = $request->user()->currentInternship()->firstOrFail();
```

The request accepts no `student_id`, `user_id`, or `internship_id`. The service
receives that already-scoped internship and may query only its work logs,
tasks, and requirements. Existing policies remain the authorization boundary
for the other student endpoints.

### Calculation unit

Create one focused service, `App\Services\OjtProgressAssistant`, with a
public operation equivalent to:

```php
public function build(
    Internship $internship,
    ?CarbonImmutable $today = null,
): array
```

The optional date makes boundary cases directly testable. Production defaults
to the current date in the configured Philippine timezone. The service returns
the exact API payload below; the controller only wraps it in the existing
`data` response convention.

### Response contract

```json
{
  "data": {
    "internship": {
      "start_date": "2026-09-01",
      "end_date": "2026-12-15",
      "work_days": [1, 2, 3, 4, 5],
      "expected_daily_minutes": 480
    },
    "progress": {
      "required_minutes": 30000,
      "rendered_minutes": 14400,
      "remaining_minutes": 15600,
      "percentage": 48
    },
    "pace": {
      "status": "on_track",
      "remaining_scheduled_days": 38,
      "required_daily_minutes": 411,
      "expected_daily_minutes": 480
    },
    "attention": {
      "items": [
        {
          "type": "requirement",
          "id": 7,
          "title": "Medical clearance",
          "due_date": "2026-09-23",
          "reason": "due_today",
          "priority": 5,
          "href": "/student/requirements"
        }
      ],
      "additional_count": 0
    },
    "completion": {
      "ready": false,
      "remaining_minutes": 15600,
      "incomplete_required_requirements": [
        {
          "id": 7,
          "title": "Medical clearance",
          "due_date": "2026-09-23"
        }
      ],
      "blockers": ["hours_remaining", "required_requirements_incomplete"]
    }
  }
}
```

`href` values are fixed application routes, not user-provided URLs. The
frontend may format minutes as hours, but it must not recalculate business
values.

### Progress rules

- `rendered_minutes` is the sum of `work_logs.rendered_minutes` whose status is
  `completed` for this internship.
- `required_minutes` is `internships.required_minutes`.
- `remaining_minutes = max(required_minutes - rendered_minutes, 0)`.
- `percentage = min(rendered_minutes / required_minutes * 100, 100)` with a
  defensive zero-required case returning `0`.
- The service exposes minutes as the authoritative values. It does not infer
  progress from time-in/time-out values on the overview request.

### Scheduled-day and pace rules

Use an inclusive date window and configured ISO weekday values:

- before the start date: count `start_date` through `end_date`;
- on or after the start date and on or before the end date: count `today`
  through `end_date`;
- after the end date: return `0` scheduled days;
- count only weekdays included in `work_days`;
- count today when it is configured;
- do not call holiday services or apply unconfigured holiday rules.

Pace is calculated as:

- `0` when no minutes remain;
- `ceil(remaining_minutes / remaining_scheduled_days)` when both values are
  positive;
- `null` when minutes remain but no scheduled days remain.

The expected pace is `expected_daily_minutes`. The payload retains the
supporting numbers so the UI can explain every status.

### Pace status rules

If the internship is missing a start/end date, has no valid configured work
days, or has an expected daily schedule outside the supported `30` to `1440`
minute range, return `pace_unavailable` with a `null` required pace. The UI
must explain that the OJT schedule is incomplete and link to the existing
profile setup page. This state is distinct from a valid schedule with zero
remaining days.

Apply rules in this order:

1. future start date → `not_started`;
2. zero remaining minutes → `complete`;
3. today after end date with minutes remaining → `deadline_passed`;
4. active period with scheduled days remaining and required pace less than or
   equal to expected pace → `on_track`;
5. active period with required pace greater than expected pace, or no scheduled
   days remaining → `at_risk`.

The status is descriptive only; it does not mutate the internship or mark an
official completion.

### Needs Attention rules

Inspect incomplete tasks and requirements from the authenticated internship.
For due dates, use these categories relative to Philippine `today`:

- due date before today → `overdue`;
- due date equal to today → `due_today`;
- due date after today and no more than three days away → `due_soon`;
- later due dates are omitted;
- completed records are always omitted;
- required requirements with no due date are included as a lower-priority
  `no_due_date` item;
- optional requirements with no due date are omitted.

Priority is fixed and lower numbers are more important:

1. deadline passed with remaining hours;
2. overdue required requirement;
3. overdue task;
4. at-risk pace;
5. required requirement due today;
6. task due today;
7. required requirement due soon;
8. task due soon;
9. incomplete required requirement without a due date;
10. overdue optional requirement;
11. optional requirement due today;
12. optional requirement due soon.

Optional requirements may appear only when overdue, due today, or due soon,
and after required items at the same urgency. Sort by priority, then due date
(`null` last), then record ID. Return at most five items and expose the number
of omitted items as `additional_count`.

The deadline item is generated from the internship state, not from a database
record, and links to Work Hours. Requirement items link to Requirements;
tasks link to Tasks.

### Completion readiness rules

`completion.ready` is true only when:

1. `rendered_minutes >= required_minutes`; and
2. every requirement with `is_required = true` has status `completed`.

Tasks never block readiness. Optional requirements never block readiness. The
service returns the remaining minutes and incomplete required requirements as
the blockers. If there are no required requirements, hours alone determine
readiness.

The UI copy must say “ready to complete” or “requirements still need
attention”; it must not claim an official school/company completion decision.

## Frontend design

### Routing and redirects

Add a protected `/student/overview` route. Authenticated navigation becomes:

`Overview → Work Hours → Tasks → Requirements`, followed by existing account
and theme controls.

Successful login uses a safe internal `redirect` query when one exists and
otherwise goes to `/student/overview`. Successful registration always starts at
`/student/overview`. An authenticated visit to `/login` or `/register` also
redirects to Overview. The logged-in brand remains a non-link as required by
the existing UX decision.

### Overview hierarchy

Overview is an interpretation and decision-support page. It summarizes the
detail pages without reproducing their management controls or full records.
Create `frontend/src/views/student/OverviewView.vue` with this visual order:

1. Page header with “Overview”, direct status copy, and compact OJT context
   (period, work days, and expected daily hours).
2. Compact Progress Summary: rendered, required, remaining hours, percentage,
   and one progress bar. Do not place a nested OJT period card here.
3. Current Pace: required pace, expected pace, scheduled days remaining, and a
   readable explanation. Make `Pace unavailable` distinct from `At risk`.
4. Needs Attention: five or fewer linked items with readable labels such as
   Overdue, Due today, Due soon, At risk, or Deadline passed. Backend priority
   numbers remain sorting metadata and are never displayed.
5. Completion Readiness: `Ready` or `Not ready`, with exact hour and required
   requirement blockers. Tasks and optional requirements are not blockers.

Use existing `ProgressBar`, `StatusBadge`, `PageHeader`, `EmptyState`, and
button/link classes where they fit. Do not create a generic dashboard/card
framework. Use a responsive single-column flow with a small responsive grid
only where it improves scanning. Preserve neutral/light styling, black primary
actions, semantic state colors, dark-mode selectors, and mobile keyboard
accessibility from `DESIGN.md`.

The page calls one `getStudentOverview()` service method. It has a loading
skeleton, a retryable error state reading “We couldn't load your OJT
overview.”, and a safe empty state reading “Complete your OJT setup before
using the progress assistant.” for a missing or not-yet-configured
internship.

## Error and data-integrity handling

- Laravel remains authoritative for authorization, date math, pace, status,
  attention, and readiness.
- A missing current internship returns the existing not-found behavior rather
  than exposing another account's data.
- Frontend API errors use safe generic copy and never expose exception details.
- No raw exception, SQL, or debug message is rendered to the student.
- The overview is read-only; work-log/task/requirement mutations continue
  through their existing endpoints and pages.

## Test design

### Backend

Add focused feature/service coverage for:

- completed versus non-completed work logs;
- percentage and remaining-minute caps;
- future-start, active, completed, deadline-passed, and at-risk statuses;
- inclusive schedule counting, configured weekdays, start/end boundaries, and
  Philippine date behavior;
- pace ceiling division, completed zero pace, and no-days-null pace;
- every attention priority/category, completed exclusion, no-due-date required
  requirements, five-item cap, and deterministic ties;
- readiness with hours incomplete, required requirements incomplete, optional
  requirements incomplete, tasks incomplete, and no required requirements;
- authenticated access and cross-account denial.

### Frontend

Add tests for:

- service request path and response unwrapping;
- compact progress, current pace/unavailable pace, attention links without
  numeric priority labels, and exact readiness blocker copy;
- loading, retryable error, empty attention, and empty internship states;
- router protection and login/register/default redirect behavior;
- Overview navigation and active state without regressing the non-link brand.

### Browser smoke

Extend the existing local smoke flow to log in, verify Overview content, add a
work log, confirm progress changes, create or complete a task/requirement as
needed for attention/readiness coverage, and verify navigation to all three
student detail pages.

## Documentation updates

Update:

- `PROJECT_CONTEXT.md` to make the Progress & Needs-Attention Assistant an
  implemented core feature and record the deterministic backend rules;
- root `DESIGN.md` with the Overview hierarchy, status/readiness copy, and
  accessibility expectations;
- `README.md` with the Overview route and the safe completion wording.

Do not add Phase 5 scope or temporary debugging notes.

## Verification exit criteria

Before reporting completion, run the repository's actual checks:

```text
cd frontend && npm run test
cd frontend && npm run build
cd backend && php artisan test
git diff --check
python scripts/phase0_smoke.py   # with the documented local servers
```

Report exact results and anything unavailable. Do not claim official OJT
completion; report only that the tracked data is ready according to the
configured rules.
