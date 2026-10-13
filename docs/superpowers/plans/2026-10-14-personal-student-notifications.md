# Personal Student Notifications Implementation Plan

> Execute sequentially in the current workspace. Preserve the existing company/intern notification workflow and unrelated working-tree changes. Do not commit or push.

**Goal:** Show personal students the existing notification bell, alert once for each missed scheduled workday without a work log, and send due-today/overdue alerts for unfinished personal tasks and requirements.

**Design:** Reuse the existing Laravel notifications table, notification writer, bell, API endpoints, and daily 9:00 a.m. Asia/Manila command. Scope all notifications to the authenticated account. Mark all as read preserves notification history. Export Summary is available on both personal-student and company-managed-intern Overviews and uses the authenticated account's internship.

## Tasks

1. Add failing backend feature tests for student notification isolation, mark-all-read history preservation, missed scheduled work hours, and personal task/requirement deadline reminders.
2. Add a failing frontend assertion that personal student navigation displays the existing notification bell.
3. Extend the existing notification route middleware to permit authenticated students, preserving the intern forced-password-change guard.
4. Extend the existing deadline command to scan personal student tasks/requirements and the previous scheduled workday for active personal internships with no work log. Use the existing recipient-scoped unique key for idempotency.
5. Notify a personal student immediately when they create an unfinished task or requirement due today, sharing the scheduler's due-today key so the scheduled run cannot duplicate it.
6. Show the existing `NotificationBell` for student accounts without changing its behavior or adding dependencies.
7. Update the notification specification, `PROJECT_CONTEXT.md`, and `DESIGN.md` to document personal alerts, 9 a.m. Manila timing, and mark-all-read history behavior.
8. Run focused Laravel and Vitest checks, the backend and frontend suites/build where feasible, and `git diff --check`.

## Verification

- Backend: `php artisan test --filter=CompanyInternNotificationTest`
- Frontend: `npm run test -- --run src/App.test.js`
- Regression: `php artisan test`, `npm run test -- --run`, `npm run build`, and `git diff --check`

## Boundaries

- Do not change the student task, requirement, or work-log workflows. Preserve Export Summary for personal students and make the same authenticated PDF available to company-managed interns.
- Do not add notification delivery channels, preferences, new tables, migrations, or new dependencies.
- Missed-work alerts evaluate only the previous Manila calendar day during the scheduled run; task/requirement overdue alerts retain existing catch-up behavior.
