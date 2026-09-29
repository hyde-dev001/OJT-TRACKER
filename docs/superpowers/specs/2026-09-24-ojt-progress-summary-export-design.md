# OJT Progress Summary Export

**Date:** 2026-09-24
**Status:** Approved written spec
**Project:** OJT Progress Tracker

## Intent

Give an authenticated student a concise, printable snapshot of their current
tracked OJT progress. The export supports personal review, saving, printing,
sharing, and project demonstration. It does not verify attendance or certify
completion.

The project is a student-only personal tracker. This is a narrowly scoped
export capability, not a reports or analytics module. `PROJECT_CONTEXT.md`
currently lists reports as out of scope; implementing this design requires
updating that product truth to record this single approved summary export.

## User outcome and acceptance criteria

From Overview, a student can download a readable A4 PDF containing the latest
tracked progress and a clear personal-use disclaimer. The export is accepted
when:

- it is available whenever the authenticated student has a configured OJT
  internship, including before any work logs exist;
- its progress, pace, Needs Attention, and completion readiness use the same
  backend-authoritative calculations as Overview;
- task and requirement counts include only records belonging to the current
  student's internship;
- no client-supplied user or internship ID can select export data;
- the download has a safe date-based filename and is readable in grayscale;
- generation shows a busy state, prevents duplicate clicks, and reports a
  recoverable error without exposing server details;
- its wording never claims official, school-approved, company-approved, or
  certified attendance or completion.

## Scope

### Included

- One `Export Summary` action on the authenticated Overview page.
- One authenticated student-scoped endpoint returning a generated PDF.
- A print-friendly 1–2 page A4 summary using existing assistant calculations
  plus small task and requirement count queries.
- Loading, duplicate-request prevention, download, and generic error feedback.
- Focused backend and frontend tests and updates to product/setup documentation.

### Explicitly excluded

- Full work-log history, a separate work-log export, CSV, XLSX, or printable
  HTML as a second user-facing export type.
- Reports pages, filters, custom date ranges, analytics, charts, scheduled
  exports, saved/generated-file storage, email delivery, or background jobs.
- School/company approval fields, signatures, logos, certification language,
  or claims of official attendance.
- A new readiness formula or frontend calculation of business values.

## Recommended user experience

Place `Export Summary` near the Overview heading, where the existing progress,
pace, attention, and readiness summary already lives. Render it after the
Overview data has loaded and is available. Do not show it in loading, error,
or no-internship states.

On activation, show `Generating…` and disable the button until the request
finishes. On success, download the PDF directly without a confirmation modal
or preview step. On failure, show: “We couldn’t generate your OJT summary.
Please try again.” Keep the button available for retry.

The export is a point-in-time snapshot generated from current saved data. It
does not create or retain a server-side file.

## Export content

### Include

1. **Student and OJT details:** display name, OJT start and target end dates,
   configured work days, expected hours per OJT day, and generated date/time
   in `Asia/Manila`.
2. **Work-hour progress:** rendered, required, and remaining hours, percentage,
   and one simple progress bar.
3. **Current pace:** readable status, remaining scheduled OJT days, required
   minutes per scheduled day, and expected minutes per OJT day.
4. **Task counts:** total, To Do, In Progress, Completed, and overdue. Overdue
   is a subset of incomplete tasks with a due date before the current date in
   the configured Philippine timezone.
5. **Required requirement counts:** total, completed, incomplete, and overdue
   required requirements. Optional requirements are omitted from this block.
6. **Needs Attention:** up to five items in the same deterministic order as
   Overview, with readable labels and no numeric priority values.
7. **Completion Readiness:** Ready or Not ready, remaining hours, count of
   incomplete required requirements, and up to five blocker titles. If more
   remain, show “and N more”.
8. **Disclaimer:** “Generated from the student’s tracked OJT data for personal
   progress monitoring. This is not official verification of attendance or
   internship completion by a school or company.”

Tasks do not block readiness. Optional requirements do not block readiness.
The existing `OjtProgressAssistant` result remains authoritative for
completion readiness and its blockers.

### Exclude

Do not include full work-log rows, time-in/time-out, accomplishment summaries,
task descriptions, requirement notes, email address, internal database IDs,
or unbounded lists. Keep the document to a concise summary rather than a
second management workflow.

## Format and document layout

Use one downloadable PDF. PDF is suitable for saving, printing, and defense
demonstration, and its layout is more consistent than browser-dependent print
HTML. The PDF must use a white background, neutral typography, restrained
semantic status accents, and readable labels when printed in black and white.
It must not inherit the app’s dark-mode background.

Suggested order:

1. Title, student name, OJT period, and generated timestamp.
2. Hours progress and current pace.
3. Task and required-requirement counts.
4. Needs Attention.
5. Completion Readiness and blockers.
6. Personal-tracking disclaimer in the footer.

Use wrapping for long names and item titles. If the content requires a second
page, keep section headings and their content together where practical. Do not
shrink text until it becomes difficult to read.

Use the filename `OJT-Progress-Summary-YYYY-MM-DD.pdf`, with the generated
date in the Philippine timezone. Omitting the student's name from the filename
reduces unnecessary personal-data exposure when files are shared.

## Backend design

Add a GET endpoint inside the existing `auth:sanctum` and `student` route
groups. The endpoint accepts no `student_id`, `user_id`, or `internship_id`.
It resolves the current internship through the authenticated user’s existing
relationship and returns not found when no internship is configured.

Reuse `App\Services\OjtProgressAssistant` for progress, pace, Needs Attention,
and completion readiness. Do not duplicate or recalculate those values. Add
only scoped aggregate counts for task statuses and overdue tasks, and required
requirement statuses and overdue requirements. Counts use the internship
relationship and the application timezone. The endpoint returns a PDF with
the appropriate content type and attachment filename.

No PDF package is currently present in `backend/composer.json` or
`frontend/package.json`. The implementation plan must select a lightweight
PDF renderer compatible with the repository’s Laravel/PHP and Render Docker
runtime. Do not add a dependency until the implementation plan is approved.
No database schema change is expected.

## Frontend design

Keep the action and download state in `OverviewView.vue`; use the existing
centralized Axios client through the OJT service module. Request a binary
response, create a temporary browser download using the server-provided
filename (with the agreed date-based filename as a safe fallback), and revoke
temporary object URLs after use. Prevent duplicate clicks with a local busy
state. Display only generic, recoverable error text.

Do not add an account-menu action or Reports route. Keep the export action
keyboard accessible and usable at mobile widths.

## Security and wording

- Enforce authentication and resolve records only from the signed-in student’s
  current internship.
- Never trust client-supplied record ownership identifiers.
- Render database text through escaped template output; do not allow stored
  titles or names to inject markup into the PDF.
- Exclude email, personal notes, and detailed logs from the summary.
- Do not say “Official OJT Complete”, “Certified OJT Completion”, “Approved by
  school”, or “Approved by company”.
- `Ready` describes only the configured tracker rules: rendered hours meet
  the requirement and all required requirements are completed.

## Edge-case behavior

| Case | Export behavior |
| --- | --- |
| No work logs / zero rendered hours | Show `0m` rendered, full remaining hours, and `0%`. |
| Required hours reached or exceeded | Show actual rendered hours, `0m` remaining, and cap percentage at `100%`. |
| No tasks or requirements | Show zero counts and a short empty-state label. |
| No required requirements | Show zero required requirements; readiness depends on hours alone. |
| Incomplete optional requirements | May be omitted from requirement counts and never block readiness. |
| Future OJT start | Reuse `Not started` pace status and display the configured start date. |
| Target date passed | Reuse `Deadline passed` status and Needs Attention item when present. |
| Invalid or missing pace schedule | Show `Pace unavailable`; do not invent pace values. |
| Overdue requirements | Count only incomplete required requirements as overdue in the required summary. |
| Many attention items | Show at most the five already returned by the assistant; do not show priority numbers. |
| Many incomplete required requirements | Show the count and up to five titles, then “and N more”. |
| Partial-hour values | Format backend integer minutes as hours and minutes. |
| Long names or titles | Wrap naturally; allow a second page rather than clipping or unreadable scaling. |
| Missing internship | Keep export unavailable; preserve the Overview no-setup state. |
| Export generation failure | Show generic retry message; retain no partial or stale download. |

## Testing plan

### Backend

- Unauthenticated request is rejected.
- Student can export their own internship; another student's data cannot be
  selected by request parameters.
- Progress, pace, Needs Attention, and readiness match the assistant output.
- Task and requirement counts handle every status, overdue boundaries, and
  empty datasets.
- Ready requires sufficient rendered minutes and all required requirements;
  tasks and optional requirements do not affect it.
- Response has PDF content type, attachment disposition, expected filename,
  and key expected text including the disclaimer.

### Frontend

- Export action appears only with loaded Overview data.
- Busy state disables the action and prevents duplicate requests.
- Successful response triggers one download and restores the button state.
- Failed response shows generic retry copy and permits another attempt.
- No pixel-based PDF snapshot test is needed.

## Risks and scope controls

- A PDF library must run in the Render Docker image; verify PHP extension and
  runtime compatibility before choosing it.
- Keep aggregation limited to counts; do not fetch or embed paginated full
  task, requirement, or work-log lists.
- Keep wording and layout distinct from official attendance or completion
  documents.
- Update `PROJECT_CONTEXT.md`, `DESIGN.md`, and `README.md` only when the
  implementation is approved and completed.

## Frozen implementation scope

One Overview button; one authenticated student-scoped PDF endpoint; existing
assistant calculations plus scoped summary counts; one 1–2 page neutral A4
PDF; safe date-based filename; generic loading/error UX; focused backend and
frontend tests; and concise documentation updates. No full log export,
additional export formats, Reports module, or file persistence.
