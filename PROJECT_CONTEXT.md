# PROJECT_CONTEXT.md

# OJT Progress Tracker - Project Context

## Product identity

OJT Progress Tracker is a small full-stack application for student interns to track their own rendered hours, tasks, deadlines, and internship requirements.

Primary user: Student Intern only.

This is a personal progress tracker. It does not certify official attendance or replace school/company verification processes.

The project must stay focused, testable, and easy to defend within a short student-project schedule. It is not a school management system, LMS, HR system, payroll system, or generic administration suite.

## Core features

1. Work-Hour Progress Tracking
2. Task & Requirement Tracking
3. Progress & Needs-Attention Assistant

The application presents one authenticated Overview for progress, required pace, Needs Attention, and completion readiness. These values are deterministic, backend-authoritative, and scoped to the current student's internship.

## End-to-end workflow

```text
Student creates an account
    -> completes Account Setup
    -> completes OJT Setup with required hours and dates
    -> enters the authenticated app
    -> records work activity
    -> backend validates and calculates rendered minutes
    -> saves completed work logs that count immediately
    -> tracks personal tasks and requirements
    -> reviews remaining or overdue items
```

The application should feel like one student workflow rather than unrelated CRUD pages.

### Student onboarding

Registration is a two-step flow. Account Setup collects the student's first name,
last name, optional suffix, email, password, and confirmation. The suffix is stored as
part of the registered display name. Email availability is checked before the
student can continue to OJT Setup. OJT Setup collects Required OJT Hours,
OJT Start Date, Target End Date, recurring OJT Work Days, and Expected Hours
per Day. The target end date is required and must be later than the start
date, while past and future start dates remain valid. Work days use unique ISO
weekday values from 1 (Monday) through 7 (Sunday). Expected hours per day is
validated from 0.5 through 24 hours and stored by the backend as integer
expected daily minutes. Both records are created atomically when the student
starts tracking.

## Technical architecture

```text
frontend/ Vue 3 + Vite + JavaScript + Router + Pinia + Axios + Tailwind
backend/  Laravel REST API + Sanctum session authentication + Eloquent
database/ MySQL/MariaDB locally; in-memory SQLite in PHPUnit
```

Vue is the primary application UI. Laravel owns validation, authorization, rendered-duration calculation, state transitions, and persistence.

## Data and business rules

### Internship

An internship belongs to one student and stores the total required OJT minutes,
required OJT start date, required target end date, recurring work days,
expected daily minutes, and active status. The student can configure the total
required hours from the Work Hours page. Unknown legacy records must not be
assigned fabricated dates or schedules. There is no coordinator or supervisor
relationship in the current product.

### Work logs

- Store rendered duration as integer minutes.
- Laravel calculates duration from time in, time out, and break minutes; client-supplied duration is ignored.
- Time out must be after time in and the break cannot consume the entire session.
- New logs are saved as `completed` so they count toward progress immediately.
- Completed logs remain editable and deletable by the student.
- A migration converts any legacy `draft` rows to `completed`.
- Progress is completed rendered minutes divided by the configurable total required minutes, capped at 100 percent.

### Tasks

- Tasks belong to the student's internship.
- Tasks are personal productivity items. They do not block completion
  readiness and must not gain an is_required field.
- Statuses are `to_do`, `in_progress`, and `completed`.
- The student can create, edit, and delete eligible tasks, start a task, and mark it completed.
- Completing a task records `completed_at`.
- Due dates must stay within the OJT start and target end dates; past dates inside that period remain useful for overdue state.
- No assignment or review workflow exists.

### Requirements

- Requirements belong to the student's internship.
- Statuses are `incomplete` and `completed`.
- The student can create, edit, delete, complete, and reopen requirements.
- Completing a requirement records `completed_at`; reopening clears it.
- Due dates must stay within the OJT start and target end dates; past dates inside that period remain useful for overdue state.
- Required flags and optional personal notes are supported.
- Document upload is out of scope.

## Authorization and security

Sanctum/session authentication remains. Every record query and mutation is scoped to the authenticated student's internship through Laravel policies and ownership checks. Frontend guards improve navigation, but backend authorization is authoritative.

Changing a password from Profile requires the current password and keeps the registration password policy.

There is no role selector, coordinator login, coordinator route, review endpoint, approval/rejection state, or coordinator management portal.

### Schedule and future progress rules

- Pace calculations derive eligible days from the student's configured
  recurring work days and target end date, using inclusive dates and ISO
  weekdays 1 (Monday) through 7 (Sunday).
- Public holidays are not automatically excluded in Phase 4 unless a later
  approved product decision changes this rule.
- A future OJT start date is valid. Work logs remain unavailable until the
  start date, and the backend rejects dates before the start, after today, or
  after the target end date.
- This personal tracker records student-entered progress; it does not certify
  official completion. Completion readiness depends on required OJT hours and
  required internship requirements, not personal tasks.
- Pace statuses are `not_started`, `on_track`, `at_risk`, `complete`,
  `deadline_passed`, and `pace_unavailable` when required schedule data is
  missing or invalid.
- Needs Attention is deterministic, sorted by fixed priority, and capped at
  five visible items.
- Completion readiness requires enough rendered minutes and every required
  requirement to be completed; tasks and optional requirements never block it.

## Explicitly out of scope

- Notifications, full reporting/analytics modules, AI, or external completion
  certification; the only export exception is the personal progress-summary PDF
- Official attendance certification or verification
- document-management or upload workflows
- school, company, HR, payroll, class, or grading modules
- mobile app, chat, video, biometrics, GPS, QR attendance, or payments

## Design principles

- Student-first and plain language.
- Minimal navigation: Overview, Work Hours, Tasks, Requirements, and the
  account menu.
- Neutral/light Cal-inspired styling with near-black primary actions.
- Status is communicated with text as well as color.
- Loading, empty, success, validation, and recoverable error states are required.
- Mobile layouts and accessible labels/focus states matter.
- Do not spend this scope on a full visual redesign; keep the current shared UI polish functional.

## Development phases

### Phase 0 - Technical foundation

Vue, Tailwind, Laravel, REST, Axios, Router, Pinia, database, and test foundation.

### Phase 1 - Student authentication

Sanctum/session login and authenticated student application routes.

### Phase 2 - Work-hour tracking

Work logs, validation, server-side rendered duration, simplified completion, and progress.

### Phase 3 - Tasks and requirements

Personal task/requirement records, due dates, completion states, and ownership.

### Phase 3.25 - Student-only simplification

Completed: remove coordinator architecture, review states, obsolete schema fields, and role routing while keeping current student features functional.

### Phase 4 - Progress and Needs-Attention assistant

Implemented: the authenticated Overview shows backend-calculated progress,
remaining scheduled days, required pace/status, up to five deterministic
attention items, and completion readiness based on required requirements.

### Phase 5 - QA and defense polish

Implemented: responsive/mobile checks, accessible dialogs and controls,
light/dark theme QA, paginated task and requirement lists with requirement
filters, deterministic demo data, final testing logs, and defense notes.

## Definition of done

A change is complete only when the requested behavior, ownership, validation, persistence, UI feedback, relevant automated tests, browser flow, and documentation are aligned without adding unrelated scope.
