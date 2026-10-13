# PROJECT_CONTEXT.md

# OJT Progress Tracker - Project Context

## Product identity

OJT Progress Tracker is a small full-stack application with two independent account paths: a student's personal tracker and a company-managed intern onboarding flow. Self-registered students continue to track their own hours, tasks, deadlines, and internship requirements.

Primary user: Student Intern.

Supporting actor: one account holder for each company, who creates and manages that company's intern accounts.

Company-managed records do not certify official attendance or completion and do not replace school/company verification processes.

The project must stay focused, testable, and easy to defend within a short student-project schedule. It is not a school management system, LMS, HR system, payroll system, or generic administration suite.

## Core features

1. Work-Hour Progress Tracking
2. Task & Requirement Tracking
3. Progress & Needs-Attention Assistant

The personal student tracker presents an authenticated Overview for progress, required pace, Needs Attention, and completion readiness. These values are deterministic, backend-authoritative, and scoped to the current student's internship. Company and company-managed intern accounts have separate onboarding workspaces.

## End-to-end workflow

```text
Personal student path:
Student creates an account
    -> completes Account Setup
    -> completes OJT Setup with required hours and dates
    -> enters the authenticated app
    -> records work activity
    -> backend validates and calculates rendered minutes
    -> saves completed work logs that count immediately
    -> tracks personal tasks and requirements
    -> reviews remaining or overdue items

Company-managed onboarding path:
Company account holder self-registers
    -> creates an intern account with a temporary password
    -> sets the intern's placement dates and work schedule
    -> intern changes the temporary password
    -> intern enters required OJT hours from school
    -> company account holder records one attendance session per workday
    -> intern reviews recorded work sessions and progress
```

The personal tracker remains independent from company-managed accounts. A person who needs both account types uses different email addresses.

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
required hours from the Work Hours page. In the company-managed path, the
internship is owned by the intern and associated with that intern's company;
the company account holder sets the dates and schedule, while the intern sets
the required hours. The required-hours target may be empty until the intern
enters it. Unknown legacy records must not be assigned fabricated dates or
schedules.

### Work logs

- Store rendered duration as integer minutes.
- Laravel calculates duration from time in, time out, and break minutes; client-supplied duration is ignored.
- Time out must be after time in and the break cannot consume the entire session.
- New logs are saved as `completed` so they count toward progress immediately.
- Completed logs remain editable and deletable by the student.
- Company-managed attendance allows one session per intern per workday. Time in captures the server's Manila-local date and time; time out keeps the session's saved break value, defaulting to zero. The company account holder can correct the work date, times, and break on open or completed entries through Edit work hours. The backend recalculates duration and status; managers cannot delete entries.
- Company-managed interns can view their own sessions and backend-calculated progress but have no attendance write controls. An open session has no rendered duration and does not count toward progress.
- A migration converts any legacy `draft` rows to `completed`.
- Progress is completed rendered minutes divided by the configurable total required minutes, capped at 100 percent.

### Tasks

- Personal student tasks belong to the student's internship. They are
  productivity items and do not block completion readiness or gain an
  is_required field.
- Statuses are `to_do`, `in_progress`, and `completed`.
- The student can create, edit, and delete eligible tasks, start a task, and mark it completed.
- Completing a task records `completed_at`.
- Due dates must stay within the OJT start and target end dates; past dates inside that period remain useful for overdue state.

Company-managed tasks also reuse this table through the company's internship.
The account holder creates an assignment and cannot edit or delete it in this
slice. The intern submits one private image or PDF with an optional comment.
Submission states are `not_submitted`, `pending_review`,
`changes_requested`, and `approved`; approval completes the task, while a
change request keeps it in progress and allows proof replacement.

### Requirements

- Requirements belong to the student's internship.
- Statuses are `incomplete` and `completed`.
- The student can create, edit, delete, complete, and reopen requirements.
- Completing a requirement records `completed_at`; reopening clears it.
- Due dates must stay within the OJT start and target end dates; past dates inside that period remain useful for overdue state.
- Required flags and optional personal notes are supported.
- Personal student requirement behavior is unchanged; personal document upload
  remains out of scope.

Company-managed requirements reuse this table through the company's
internship. The account holder creates assignments with the existing required
flag, defaulting to required, and cannot edit or delete them in this slice.
The intern submits one private image or PDF with an optional comment. The same
submission states apply; approval completes the requirement, while a change
request leaves it incomplete and permits a replacement submission.

## Authorization and security

Sanctum/session authentication remains. Personal student records are scoped to
the authenticated student's internship. A company account holder can manage
only interns belonging to that company; an intern can update only their own
required-hours target and password. Frontend guards improve navigation, but
backend authorization is authoritative.

Personal students can change their password from Profile using the current
password. Company-created interns must replace the temporary password on first
sign-in. Their company account holder can reset a forgotten password by setting
a new temporary password; the intern must replace it at the next sign-in.

Roles are assigned by the backend. The company account holder is the only
company role; there are no extra staff seats, administrator approval, company
verification, account linking, or invitation flow. Slice 2 adds company-managed
attendance and read-only intern work hours. Slice 3 adds company-managed task
and requirement assignment, private proof submission, and company review.
Personal task and requirement APIs remain separate and unchanged.

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
- Company-managed records also do not certify official school or company
  attendance or completion.
- Pace statuses are `not_started`, `on_track`, `at_risk`, `complete`,
  `deadline_passed`, and `pace_unavailable` when required schedule data is
  missing or invalid.
- Needs Attention is deterministic, sorted by fixed priority, and capped at
  five visible items.
- Completion readiness requires enough rendered minutes and every required
  requirement to be completed; tasks and optional requirements never block it.

## Explicitly out of scope

- Email, browser push, SMS, real-time sockets, full reporting/analytics modules,
  AI, or external completion
  certification; the only export exception is the progress-summary PDF for
  personal students and company-managed interns
- Official attendance certification or verification
- submission or review history, multiple proof files per item, or public proof URLs
- additional company roles or staff seats, company verification, invitations,
  or administrator approval
- assignment editing/deletion or email workflows
- HR, payroll, class, grading, or school-management modules
- mobile app, chat, video, biometrics, GPS, QR attendance, or payments

## Design principles

- Plain language for personal students, company account holders, and
  company-managed interns.
- Keep student navigation focused on Overview, Work Hours, Tasks, Requirements,
  and the account menu. Company navigation includes intern accounts,
  Attendance, Tasks, and Requirements; company-managed intern navigation
  includes OJT account setup, read-only Work Hours, Tasks, and Requirements.
  Company, intern, and personal-student headers include an in-app notification
  bell. Personal students receive missed-scheduled-workday and task/requirement
  due reminders without changes to their tracking workflows.
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

### Phase 3.25 - Personal student workflow simplification

Completed: simplify the original personal student tracker by removing its
coordinator architecture, review states, obsolete schema fields, and role
routing while keeping student features functional. Slice 1 later added a
separate company-managed onboarding path without changing that personal flow.

### Slice 1 - Company registration and intern account setup

Adds self-service company registration, one company account holder, manual
intern account creation with temporary passwords, forced first-sign-in password
replacement, intern-entered required hours, and company-managed placement dates
and schedules. Slice 2 adds company-recorded attendance and intern read-only
work-hour history. Slice 3 adds company task and requirement assignment,
private intern proof submission, and company approval/change requests while
preserving personal student workflows.

### Slice 2 - Company attendance and intern work hours

Adds one company-managed attendance session per intern per workday. The company
account holder records time in and time out; break minutes default to zero and
can be corrected on open or completed entries through Edit work hours. The
backend calculates rendered duration. Interns
can view their own sessions and progress without attendance controls. Personal
student work logging remains unchanged. Company-managed records do not certify
official school or company attendance or completion.

### Slice 3 - Company tasks, requirements, and proof review

Reuses the existing task and requirement tables for company-owned internships.
Company account holders create assignments and review private intern image or
PDF proof; interns can submit and replace proof after a change request.
Approval completes the item. The personal student task and requirement flows
remain unchanged. Assignment editing/deletion, submission history,
and public proof URLs are not included.

Company-managed assignment, proof-submission, review, due-today, and overdue
events appear in a paginated in-app notification list for the company account
holder or assigned intern. Notifications link to the exact authorized item;
company links also select the intern. Personal students receive one alert for
each previous scheduled workday without a work log and due-today/overdue alerts
for unfinished tasks and requirements. A personal task or requirement created
with today's due date receives its due-today notification immediately. The
scheduled checks run daily at 9:00 a.m. Asia/Manila, so local development and
deployment must run Laravel's scheduler. Mark all as read preserves
notification history.

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
