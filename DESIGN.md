# OJT Progress Tracker Design Reference

## Product direction

OJT Progress Tracker is a calm, student-first personal tracker for rendered OJT hours, personal tasks, and internship requirements. It is Cal-inspired in restraint and clarity, but it is not a scheduling product and must not copy scheduling-specific Cal.com UI.

Primary user: Student Intern.

The interface should help a student answer three questions quickly:

1. How many rendered OJT hours have I logged?
2. What personal task or internship requirement needs attention?
3. What pace do I need to maintain to finish on time?
4. What can I record or update next?

Phase 4 adds a deterministic Overview for Needs Attention, Required Pace,
On Track/At Risk, and Completion Readiness. It is not an AI or chatbot
feature, and it does not certify official school or company completion.

## Visual language

- Light, neutral canvas with white raised surfaces.
- Near-black primary actions and headings.
- Slate borders and secondary text.
- Calm spacing, clear grouping, and modest shadows.
- No gradients, glassmorphism, decorative charts, marketing footer, or dashboard-card overload.
- Use semantic colors only for state:
  - green: saved/completed/success;
  - amber: attention/in-progress;
  - red: overdue/error/destructive;
  - gray: neutral/to-do/incomplete.
- Never communicate status or errors through color alone; pair color with text and, where useful, an icon.

## Tokens

Use the existing Tailwind utility conventions and shared components.

| Role | Direction |
| --- | --- |
| Canvas | very light slate/neutral |
| Surface | white |
| Text primary | slate-950 / near-black |
| Text secondary | slate-600 |
| Border | slate-200/300 |
| Primary action | near-black with white text |
| Focus | visible near-black ring |
| Success | emerald text/background pair |
| Attention | amber text/background pair |
| Error/destructive | red text/background pair |

Use a 4px spacing rhythm, standard form controls of at least 40px height, and touch targets of at least 44px where practical. Prefer the system sans stack already used by the app. Keep body text at a readable size and preserve browser zoom.

## Information architecture

The authenticated navigation is intentionally small:

- Overview
- Work Hours
- Tasks
- Requirements
- Account menu for Profile & Password and Sign Out

### Authenticated account area

- Account details belong in a compact avatar dropdown.
- The full student name must not occupy permanent navbar space.
- Sign out belongs inside the account menu and keeps confirmation behavior.
- The theme toggle lives beside the account control.

### Theming

- The project supports Light and Dark modes across public, auth, and authenticated pages.
- A manually selected theme persists locally under the user's browser profile.
- When no manual choice exists, the operating-system preference is used.
- Dark mode uses layered neutral surfaces and adjusted semantic colors rather than simple color inversion.

Overview is the authenticated landing destination because it gives the
student the primary progress signal and the next actionable context. Work
Hours remains the detailed source for daily rendered logs.

## Overview

Overview is an interpretation and decision-support page. It summarizes the
detail pages without becoming another Work Hours, Tasks, or Requirements page.

The Overview hierarchy is:

1. Header/context: current OJT period, selected work days, and expected daily
   hours as compact secondary context.
2. Compact Progress Summary: rendered, required, remaining, percentage, and one
   progress bar. Do not add a nested OJT period card or Work Hours controls.
3. Current Pace: required pace, expected pace, scheduled days remaining, and a
   direct explanation of the status.
4. Needs Attention: up to five actionable links with readable labels such as
   Overdue, Due today, Due soon, At risk, or Deadline passed. Never show the
   backend's numeric priority.
5. Completion Readiness: Ready or Not ready with exact hour and required
   requirement blockers. Tasks and optional requirements never block readiness.

The backend owns all calculations. The frontend formats minutes for display
and links attention items to Work Hours, Tasks, or Requirements. Pace statuses
are `Not started`, `On track`, `At risk`, `Complete`, `Deadline passed`, and
`Pace unavailable` when the OJT schedule is incomplete or invalid.
The assistant uses inclusive configured workdays, does not apply holidays,
and never claims official school/company completion. A pace-unavailable state
must not be presented as At risk merely because pace inputs are missing.

Public navigation may include Home, About, and Sign in/Register. The product brand is a visual identity element; it must not unexpectedly redirect an authenticated student away from the current workflow.

## Work Hours

Work Hours is the detailed authenticated work-log page.

Hierarchy:

1. Page title and one clear Add work log action.
2. Rendered hours progress with completed, required, and remaining values.
3. Configurable total required OJT hours.
4. OJT period showing start and required target end date.
5. Work-log list with human-readable dates, times, and rendered duration.
6. Pagination when records exceed one page.

New work logs are completed immediately and count toward progress. Do not show Mark as complete, Save draft, coordinator review, approval, or reviewer controls. Each log can be viewed, edited, or deleted.

Create/edit uses the existing accessible modal pattern:

- dark translucent backdrop;
- clear title and description;
- visible labels;
- keyboard focus and Escape/close behavior;
- local field errors;
- disabled submit while saving;
- success/error feedback through the existing alert modal/toast pattern.

If the OJT start date is in the future, disable Add work log and show:

> Your OJT hasn't started yet. Work logging will be available on <date>.

Do not create contradictory date bounds where minimum is after maximum.

## Registration

Registration is a centered two-step setup flow without the public navbar.

Step 1: name, optional suffix, email, password, confirmation. Check email availability before advancing; an existing email stays on Step 1 with a field-level message.

Step 2: required OJT hours, OJT start date, target end date, recurring OJT work days, and expected hours per day.

The schedule controls should be compact, labeled weekday checkboxes. Keep the default Monday–Friday selection visible but allow the student to change it. Expected hours per day is numeric and limited to a reasonable 0.5–24 range.

Passwords show live requirement feedback for:

- at least 12 characters;
- uppercase letter;
- lowercase letter;
- number;
- symbol.

Show each rule as met/not met with text and color.

## Tasks

Tasks are personal productivity items. Use a compact list with readable states:

- To Do
- In Progress
- Completed

The list supports add/edit/delete where allowed, Start, and Mark completed actions. Completed tasks remain readable and do not block OJT completion readiness. Do not add an is_required field or coordinator assignment/review semantics.

Large task lists use server-backed pagination and status filters without
changing the personal workflow.

Use due-date visibility and an explicit Overdue label when appropriate. Do not turn tasks into a second work-hours dashboard.

## Requirements

Requirements are internship checklist items and may be Required or Optional.

Use the states:

- Incomplete
- Completed

Incomplete requirements can be added, edited, or deleted. Completed requirements are read-only for direct edit/delete and can be reopened with Mark incomplete. After reopening, normal edit/delete actions return.

Due dates and the required/optional distinction should be visible in the list. Personal notes remain secondary content. Use confirmation for destructive deletion and clear success/error feedback for every action.

Large requirement lists use server-backed pagination. Filters include All,
Incomplete, Completed, and Overdue; overdue means an incomplete requirement
with a due date before today in the Philippine business timezone.

## Feedback and state coverage

Every page must have purposeful:

- loading state;
- empty state;
- recoverable error state;
- disabled/busy state;
- field-level validation;
- success confirmation for saves/deletes/transitions.

Avoid permanent green inline banners for routine actions when a short success alert/modal is clearer. Keep messages plain and non-sensitive. Authentication errors must remain generic.

## Accessibility and responsive behavior

- Keep labels associated with controls; do not rely on placeholders.
- Preserve visible focus and logical Tab order.
- Modals must restore focus to the launching action when closed.
- Use buttons for actions and links for navigation.
- Keep destructive actions separated and confirm them.
- At mobile widths, stack grids and keep actions reachable without horizontal page scrolling.
- Test registration and future-start Work Hours at a narrow viewport.
- Do not hide essential meaning at larger text sizes or browser zoom.

## Explicitly avoid

- scheduling-specific navigation, calendar grids, booking slots, or Cal.com marketing patterns;
- generic or empty dashboard shells;
- decorative charts or arbitrary combined progress scores;
- gradients, glassmorphism, excessive rounded cards, and large marketing footers;
- coordinator, supervisor, approval, review, notification, analytics, or AI UI;
- client-side progress calculations, arbitrary combined scores, or claims of
  official completion;

## Reference precedence

1. PROJECT_CONTEXT.md — product and business truth.
2. DESIGN.md — this OJT-specific UI reference.
3. docs/design-reference/CAL_DESIGN.md — preserved raw Cal-inspired reference only.
