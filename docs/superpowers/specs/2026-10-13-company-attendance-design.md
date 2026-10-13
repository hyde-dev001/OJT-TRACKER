# Company Attendance and Work-Hour Tracking

- **Status:** Approved for planning
- **Date:** 2026-10-13
- **Scope:** Company-managed attendance and intern read-only work hours

## Purpose

Add live attendance recording to the company-managed OJT flow. A company account holder starts and ends one work session per intern per workday, enters break minutes at time-out, and can correct a saved session. The intern can view attendance and work-hour progress but cannot change attendance.

The existing personal student work-log workflow remains unchanged. This slice does not add task or requirement assignment, proof uploads, or review workflows.

## Agreed product rules

- Only the company account holder can start, end, or correct attendance for interns owned by that company.
- The company account holder can edit an intern's saved work-hour entries, including completed sessions, by correcting the work date, time-in, time-out, and break minutes.
- One session is allowed per intern per workday.
- Starting a session records the server's current date and time in the configured application timezone (`Asia/Manila` by default).
- Ending a session records the server's current time. The account holder enters the total break minutes at time-out.
- The backend validates time ordering and break duration and calculates rendered minutes. Client-supplied rendered minutes or status are ignored.
- Attendance can be recorded before the intern enters required OJT hours. Completed minutes are available, but percentage progress stays unavailable until the target is set.
- Intern attendance access is read-only. An intern cannot start, end, edit, or delete a session.
- Work-hour records are for personal monitoring and do not certify official company or school attendance or completion.

## Data model and calculations

Reuse the existing `work_logs` table and `WorkLog` model. Its unique internship/date key already enforces one session per intern per workday, and its existing duration calculation stores completed work as integer minutes.

The schema must allow `time_out` and `rendered_minutes` to be `null` for an open session. A newly started company session stores the internship, work date, server-recorded time-in, zero break minutes, null time-out and rendered minutes, and the `in_progress` status. On time-out, the backend stores the current time, the submitted break minutes, the calculated rendered minutes, and the `completed` status.

Only completed sessions contribute to progress. The current `Internship::progressSummary()` already sums completed work logs and returns a null percentage when required minutes are unset. Student-created work logs remain completed records and continue to use the existing validation and routes.

All sessions are same-day sessions. A time-out must be later than time-in, and break minutes must be non-negative and less than the elapsed session. Work dates must follow the intern's placement dates and configured workdays. A correction may retain a date that became off-schedule after a placement update; changing the date must pass current date and schedule validation and cannot conflict with another session.

## Workflows

### Company holder starts and ends a session

1. The account holder opens company attendance and selects one of their interns.
2. The account holder selects **Time in**. The backend verifies company ownership, placement dates, and the configured workday, then creates today's open session.
3. At the end of duty, the account holder selects **Time out** and enters total break minutes.
4. The backend records the current time, validates the duration and break, calculates rendered minutes, and closes the session.
5. A duplicate start for the same intern and workday returns a field-level validation error. The database unique key remains the final concurrency guard.

If time-out is missed, the session remains open. The account holder can close it through correction by entering the missing time-out and break minutes.

### Company holder corrects a session

The account holder can edit the work hours for any session belonging to one of their interns, including completed sessions. They can correct the work date, time-in, time-out, and break minutes. The backend recalculates rendered minutes and derives the session state from whether time-out exists. Corrections cannot set company, intern, internship, status, or rendered minutes directly. Sessions cannot be deleted in this slice.

### Intern views work hours

The intern opens a read-only Work Hours page showing the progress summary and a paginated list of their own sessions. An open session is labeled **In progress** and has no rendered duration. A completed session shows its date, time-in/time-out, break minutes, and rendered duration. No write controls are available to the intern.

If required hours have not been set, completed hours may still be shown, while required hours, remaining hours, and percentage remain unavailable.

## Authorization and API boundaries

- Company routes require the authenticated `company` role. Ownership is derived from the account holder's company relation; request data never chooses `company_id` or `internship_id`.
- Company attendance queries scope the requested intern to the authenticated company's internships and the intern role. Work-log identifiers must also be scoped to that intern's internship.
- Intern attendance routes require the `intern` role and completed password setup, and always use that intern's own current internship.
- Intern routes are read-only. Company attendance actions are inaccessible to interns and personal students.
- Existing `/student/work-logs` routes and student authorization remain unchanged.
- Validation failures are field-level and do not expose internal exception details.

Proposed routes:

- `GET /company/interns/{intern}/work-logs` : paginated sessions for an owned intern.
- `POST /company/interns/{intern}/work-logs/time-in` : start today's session; no client date or time is accepted.
- `POST /company/interns/{intern}/work-logs/time-out` : close today's open session with `break_minutes`.
- `PUT /company/interns/{intern}/work-logs/{workLog}` : correct an owned session's date and time values.
- `GET /intern/work-logs` : paginated read-only sessions for the authenticated intern.

The company holder's workspace gains an Attendance page with an intern selector and session controls. The intern workspace gains a Work Hours page. Both pages use the existing API client, route-role guards, loading/error/empty states, and responsive navigation patterns.

## Scope boundaries

This slice includes:

- live company time-in and time-out;
- one session per intern per workday;
- break minutes entered at time-out;
- server-side rendered-minute calculation;
- company correction of its own interns' sessions, including closing an open session;
- intern read-only session history and work-hour progress;
- backend and frontend tests that preserve the personal student flow.

This slice does not add multiple sessions per day, overnight sessions, attendance deletion, change-history auditing, automatic reminders, location tracking, proof uploads, company task/requirement assignment, or official attendance certification.

## Acceptance criteria

1. A company account holder can start one session for an owned intern on a configured workday within the placement period.
2. The backend captures the work date and time-in; clients cannot supply those values for a normal time-in action.
3. A second session cannot be started for the same intern and workday, including under concurrent requests.
4. A company account holder can end today's open session and enter break minutes; the backend captures time-out and calculates rendered minutes.
5. Invalid time ordering and invalid break minutes are rejected with field-level errors.
6. An open session has no rendered minutes and does not count toward completed hours or progress.
7. A company account holder can edit open and completed work-hour entries for their own interns; corrections recalculate duration and cannot directly set derived fields.
8. An open session with a missed time-out can be closed by correction.
9. An intern can view only their own paginated sessions and work-hour summary, with no attendance write actions.
10. Progress percentage remains unavailable until the intern enters required OJT hours.
11. Existing personal student work-log create, view, edit, delete, and progress behavior continues unchanged.
12. Cross-company requests and attempts by students or interns to use company write endpoints are rejected by backend authorization.

## Verification plan

Backend feature tests cover start/time-out, server-captured values, duplicate and concurrent starts, workday and placement validation, break and time validation, computed duration, open-session progress behavior, corrections, cross-company isolation, intern read-only access, and unchanged student work-log behavior. Frontend tests cover company attendance loading/empty/error states, start/end/correction actions, intern work-hours display, and absence of intern write controls. Build and browser verification cover the company and intern flows at desktop and mobile widths.

## Known risks

- A missed time-out leaves an open session until the company account holder corrects it.
- Corrections overwrite the saved values; this slice does not retain an audit history.
- The existing work-log schema currently requires time-out and rendered minutes, so a forward migration must make those fields nullable while keeping student request validation required. Rolling the schema back while open sessions exist may be blocked by those null values.
- These records are monitoring data and are not proof of official attendance or completion.
