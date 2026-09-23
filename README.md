# OJT Progress Tracker

OJT Progress Tracker is a student-only personal tracker for rendered internship hours, tasks, deadlines, and requirements. It does not certify official attendance or replace school/company verification processes.

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

The student-only migration removes obsolete role, coordinator, assignment, submission, and review columns. Back up a populated production database before running `php artisan migrate`; its rollback can recreate the empty columns but cannot restore deleted values.

## Demo account

- Student: `student@example.com` / `OjtTracker!2026`

The seeder is idempotent and creates one active student internship with 500 total required hours, a 2026-09-01 through 2026-12-15 period, Monday–Friday work days, 8 expected hours per day, and a small representative set of logs, tasks, and requirements. Generated browser-smoke accounts are cleaned by the seeder.

## Current workflow

- Registration uses Account Setup (first name, last name, optional suffix, email, password, and confirmation) followed by OJT Setup (required hours, OJT start date, required target end date, recurring work days, and expected hours per day). The account and internship are created together, and the student enters Overview after successful registration.
- Overview: review backend-calculated rendered progress, required pace/status, up to five Needs Attention items, and completion readiness.
- Work Hours: configure total required OJT hours, add completed logs that count immediately, edit/delete logs, and view server-calculated rendered progress.
- Tasks: add/edit/delete eligible tasks, start them, mark them completed, and page/filter larger lists.
- Requirements: add/edit/delete personal requirements, keep notes, complete them, mark them incomplete again, and filter overdue items.

All records are scoped to the authenticated student's internship. The backend validates inputs, calculates rendered minutes, controls transitions, and ignores client-supplied duration totals.

Future OJT start dates are valid; Work Hours disables logging until the start
date and the backend rejects dates before start, after today, or after target
end. Overview calculates pace from inclusive configured workdays and uses
required requirements, not tasks or optional requirements, for readiness.

## API shape

Authenticated feature routes remain under `/api/student`:

- `/overview` (backend-calculated student progress assistant)
- `/internship` (GET/PUT for the total required OJT hours)
- `/work-logs` and `/work-logs/{id}` (completed on save; editable/deletable)
- `/tasks` (paginated; `filter=all|to_do|in_progress|completed`), `/tasks/{id}/start`, and `/tasks/{id}/complete`
- `/requirements` (paginated; `filter=all|incomplete|completed|overdue`), `/requirements/{id}/complete`, and `/requirements/{id}/incomplete`

There are no coordinator routes or review endpoints.

Public authentication routes include `/api/login` and `/api/register`.

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
browser smoke verification. Notifications, reports, analytics, AI, and
official attendance or school/company completion certification remain out of
scope.
