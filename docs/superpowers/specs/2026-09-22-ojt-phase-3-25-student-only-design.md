# Phase 3.25 — Student-Only OJT Tracker

## Goal

> ARCHIVED / HISTORICAL: Phase 3.25 is complete; this document is retained
> for implementation history.

Remove the unused coordinator/supervision workflow and leave a personal tracker for one authenticated student. The current student URLs remain to avoid unnecessary route churn, and the existing UI polish remains in place; this task is not the Phase 3.5 visual redesign or Phase 4 assistant work.

## Product contract

- The only application user is the student intern.
- Authentication remains Sanctum/session-based, with login returning the student identity but no role-driven redirect.
- The authenticated navigation is Work Hours, Tasks, Requirements, and Logout.
- A student's internship owns its work logs, tasks, and requirements.
- The application tracks personal progress and does not certify official attendance or replace school/company verification.

## Data and workflow

- `internships` no longer stores `coordinator_id`.
- Work logs use `draft` and `completed`. Laravel calculates `rendered_minutes`; only completed logs count toward progress. Draft logs are editable/deletable, and completed logs remain viewable but are not reviewable.
- Tasks use `to_do`, `in_progress`, and `completed`. The student creates/edits/deletes eligible tasks, starts them, and marks them completed. Completion sets `completed_at`.
- Requirements use `not_completed` and `completed`. The student creates/edits/deletes eligible requirements and can complete or reopen them. Completion sets or clears `completed_at`. Personal notes remain supported; document upload is out of scope.
- Obsolete role, assignment, submission, and review columns are removed by a new migration after all call sites are removed. Existing migration files are not rewritten and no database reset is used.

## API and authorization

Only `/api/user`, `/api/logout`, and the existing `/api/student/...` feature routes remain. Student controllers use policies and internship ownership checks for every record mutation; coordinator controllers, endpoints, role middleware, review requests, and review resource fields are removed.

## Verification

- Backend feature tests cover student ownership, server-calculated duration, work-log progress, simplified task transitions, requirement toggling, timestamps, and obsolete endpoint absence.
- Frontend tests cover student-only routing/navigation, simplified work-log/task/requirement actions, progress copy, and API error handling.
- The browser smoke test performs login, work-log completion/progress, task start/completion, requirement completion, and logout as the demo student.
- Run backend tests, frontend tests, frontend build, smoke, `git diff --check`, and a repository search for stale coordinator/review workflow references.
# Historical document — superseded by the current student-only implementation documented in `PROJECT_CONTEXT.md` and `README.md`.
