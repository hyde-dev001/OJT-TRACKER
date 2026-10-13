# OJT Progress Tracker

OJT Progress Tracker preserves a personal student tracker for rendered internship hours, tasks, deadlines, and requirements, and adds a separate company-managed intern onboarding path. Company-managed records do not certify official attendance or completion or replace school/company verification processes.

## Stack

- Frontend: Vue 3, Vite, Tailwind CSS, Vue Router, Pinia, Axios
- Backend: Laravel 12 REST API with Sanctum session authentication
- Database: MySQL/MariaDB locally; PHPUnit uses in-memory SQLite
- Tests: PHPUnit/Laravel, Vitest, Vue Test Utils, and Playwright smoke checks

## Local setup

Backend:

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Frontend, in a second terminal:

```powershell
cd frontend
npm install
npm run dev
```

The frontend expects the API at `http://localhost:8000` and normally runs at `http://localhost:5173`. Date-only OJT rules use the Philippine timezone (`Asia/Manila`, UTC+8).

## Vercel frontend deployment

Set the Vercel project root directory to `frontend`, use `npm run build` with
`dist` as the output directory, and set `VITE_API_BASE_URL` to `/api`. The
frontend's `vercel.json` proxies `/api/*` and `/sanctum/*` to the Render API so
Sanctum session and CSRF cookies stay same-origin in the browser.

## Render backend deployment

The Laravel API includes `backend/Dockerfile` for a Render Web Service. Use
the `main` branch, set the root directory to `backend`, choose `Docker`, and
set the health check path to `/api/health`. Add production `APP_*`, `DB_*`,
`SESSION_*`, and `SANCTUM_STATEFUL_DOMAINS` values in Render Environment
Variables; do not upload the local `.env`. After the first deploy, run
`php artisan migrate --force` in the Render Shell before using the API.

The earlier student-workflow cleanup migration removed obsolete coordinator, assignment, submission, and review data. Back up a populated production database before running migrations; its rollback cannot restore deleted values. The onboarding migration adds company ownership, account roles, and support for an intern to enter required hours after account creation.

## Demo account

- Student: `student@example.com` / `OjtTracker!2026`

The seeder is idempotent and creates one active personal student internship with 500 total required hours, a 2026-09-01 through 2026-12-15 period, Monday–Friday work days, 8 expected hours per day, and a small representative set of logs, tasks, and requirements. Generated browser-smoke accounts are cleaned by the seeder. No company account is seeded.

## Current workflow

- Registration uses Account Setup (first name, last name, optional suffix, email, password, and confirmation) followed by OJT Setup (required hours, OJT start date, required target end date, recurring work days, and expected hours per day). The account and internship are created together, and the student enters Overview after successful registration.
- Company account holders self-register, then manually create intern accounts using unique email addresses and temporary passwords shared in person. They configure placement dates and work schedules; interns replace the temporary password at first sign-in and enter their required OJT hours. The company account holder can reset an intern password, requiring another password change at next sign-in.
- Company Attendance: the account holder records one time-in/time-out session per intern per workday, enters break minutes at time-out, and can correct open or completed entries. The backend recalculates rendered duration. Company-managed interns can only view their own work sessions and progress; open sessions have no duration. These records are not official attendance certification.
- Overview: review backend-calculated rendered progress, required pace/status, up to five Needs Attention items, and completion readiness; download a personal-use A4 PDF summary of the current tracked progress.
- Work Hours: configure total required OJT hours, add completed logs that count immediately, edit/delete logs, and view server-calculated rendered progress.
- Intern Work Hours: company-managed interns review backend-calculated progress and paginated sessions without attendance controls. The personal student Work Hours page is unchanged.
- Tasks: add/edit/delete eligible tasks, start them, mark them completed, and page/filter larger lists.
- Requirements: add/edit/delete personal requirements, keep notes, complete them, mark them incomplete again, and filter overdue items.
- Company Tasks and Requirements: account holders assign items to their own interns and review submitted proof. Interns submit one private image or PDF up to 10 MB with an optional comment; they may resubmit after a change request. Approval completes the assigned item. Personal student task and requirement workflows remain unchanged.

Personal student records are scoped to the authenticated student's internship. Company account holders can access only their own interns and attendance records; company-managed interns can update only their own required hours and password and can view only their own attendance. The backend validates inputs, calculates rendered minutes, controls transitions, and ignores client-supplied duration totals.

Future OJT start dates are valid; Work Hours disables logging until the start
date and the backend rejects dates before start, after today, or after target
end. Overview calculates pace from inclusive configured workdays and uses
required requirements, not tasks or optional requirements, for readiness.

## API shape

Personal student feature routes remain under `/api/student`:

- `/overview` (backend-calculated student progress assistant)
- `/overview/export` (download a personal progress-summary PDF; not official attendance or completion verification)
- `/internship` (GET/PUT for the total required OJT hours)
- `/work-logs` and `/work-logs/{id}` (completed on save; editable/deletable)
- `/tasks` (paginated; `filter=all|to_do|in_progress|completed`), `/tasks/{id}/start`, and `/tasks/{id}/complete`
- `/requirements` (paginated; `filter=all|incomplete|completed|overdue`), `/requirements/{id}/complete`, and `/requirements/{id}/incomplete`

Company onboarding routes include `/api/company/register`, `/api/company/interns`, placement updates, and company-scoped password resets. Company attendance routes include `/api/company/interns/{intern}/work-logs` for list/start/time-out/correction. Company-managed intern routes include `/api/intern/password`, `/api/intern/internship`, and read-only `/api/intern/work-logs`. Company task and requirement routes are scoped under `/api/company/interns/{intern}` for assignment creation, review, and proof download; `/api/intern/tasks` and `/api/intern/requirements` provide private intern lists, submissions, and proof downloads. Proof files use Laravel's private local disk and never appear at public URLs. Assignment editing/deletion, submission history, and notifications are not included.

Public authentication routes include `/api/login`, `/api/register` for personal students, and `/api/company/register` for company account holders.

## Verification

```powershell
cd backend
php artisan test

cd ..\frontend
npm.cmd run test -- --run
npm.cmd run build
```

The browser smoke flow can be run with the local servers available:

```powershell
python scripts/phase0_smoke.py
```

## Phase 5 status

The final stabilization pass covers responsive/mobile checks, accessible
dialogs and controls, light/dark theme QA, paginated task and requirement
lists, safe error states, deterministic demo data, automated tests, and
browser smoke verification. Slice 1 adds company registration and intern
account onboarding; Slice 2 adds company-recorded attendance and read-only
intern work hours; Slice 3 adds company task/requirement assignment and private
proof review while preserving the personal student tracker. Submission
history, assignment editing/deletion, notifications, reports, analytics, AI,
and official attendance or school/company completion certification remain out of scope. The
personal Overview progress-summary PDF is the only export exception; it is not
official attendance or completion verification.
