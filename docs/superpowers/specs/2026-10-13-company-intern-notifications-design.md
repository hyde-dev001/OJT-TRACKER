# Company-Managed OJT Notifications

- **Status:** Approved for implementation
- **Date:** 2026-10-13
- **Scope:** In-app notifications for company account holders, company-managed interns, and personal students

## Purpose

Give company account holders and company-managed interns a clear in-app place to see assignment, proof-review, and deadline updates. Add a personal-student bell for missed scheduled work hours and task/requirement due reminders without changing personal tracking workflows.

This specification adds notifications to the existing company-managed task and requirement workflow. It does not add email, browser push, real-time sockets, new account roles, or changes to assignment and review rules.

## Agreed product rules

- Notifications are delivered in the app through a bell and notification list. No email or browser push is sent.
- The bell is available to company account holders, company-managed interns, and personal students. Company-managed events stay limited to company holders/interns; personal alerts are generated only for their owning student account.
- Notifications are persisted per recipient and have unread/read state. The newest notifications appear first; the list is paginated. Event details stay unchanged after creation; reading changes only the read timestamp.
- Selecting a notification marks it read and opens the relevant work-hours, task, or requirement page. Company pages also select the relevant intern and highlight the item, including when it is outside the currently loaded page. **Mark all as read** clears unread state while keeping notification history.
- Every event notification has a deterministic recipient-scoped deduplication key enforced by a database unique constraint. Concurrent duplicate writes are ignored safely.
- Assignment, proof-submission, and review notifications are persisted in the same database transaction as the successful workflow change. Failed validation, failed state transitions, and rolled-back transactions do not leave notifications.
- Deadline checks use the current Philippine-local date. A missed run catches up one overdue alert for still-actionable past-due items; it does not send a stale **Due today** alert after the due date has passed.
- Personal alerts use the same daily 9:00 a.m. Asia/Manila check. It sends one **Work hours not logged** alert for yesterday when yesterday was a scheduled workday within the student's active OJT dates and no work log exists. The missed-hours check covers the previous calendar day only; it does not backfill older missed days after an outage.
- Personal tasks and requirements receive one **Due today** alert on their due date and one **Overdue** alert on a later scheduled run if they remain unfinished. They use the existing database deduplication and catch-up behavior.
- An assignment created on its due date also triggers its **Due today** alert immediately, so it is not missed after the daily scan has run.
- A personal student-created task or requirement with today's due date triggers the same **Due today** alert immediately after successful creation, sharing the scheduler's deduplication key.
- Notification loading and mark-read failures show recoverable feedback. A failed load is not presented as an empty list, and a failed mark-read operation does not optimistically clear unread state.
- Notification APIs derive the recipient from the authenticated user. Users can read or update only their own notifications.
- The company account holder is notified when an intern submits or resubmits task or requirement proof. In this workflow a task becomes complete only after company approval, so the actionable company event is proof submitted for review.
- The intern is notified when the company account holder assigns a task or requirement, and when a submitted item is approved or has changes requested.
- The existing `changes_requested` state is described to users as **Changes requested**, rather than a terminal rejection, because the intern can submit proof again.
- The intern receives one **Due today** notification on the due date and one **Overdue** notification the following day if the item still needs action. There are no repeated daily overdue notifications.
- Approved/completed items and items pending company review do not receive deadline reminders. Items with changes requested remain actionable and can receive due-date reminders. Items without a due date do not receive deadline reminders.
- Deadline dates use the application's Philippine timezone. The scheduled check runs daily at 9:00 a.m. Asia/Manila.
- Notifications remain available after the intern completes first-login password setup; assigned notifications created before setup are not discarded.
- The system does not notify a company account holder about their own approval action.

## Notification events and recipients

| Event | Recipient | Notification |
| --- | --- | --- |
| Company account holder creates a task | Assigned intern | New task assigned; include its title and due date when present |
| Company account holder creates a requirement | Assigned intern | New requirement assigned; include its title and due date when present |
| Intern submits or resubmits task proof | The company account holder who owns the internship | Task proof submitted; review needed |
| Intern submits or resubmits requirement proof | The company account holder who owns the internship | Requirement proof submitted; review needed |
| Company account holder approves a task or requirement | Assigned intern | Item approved |
| Company account holder requests changes | Assigned intern | Changes requested; the item remains open for resubmission |
| An actionable item reaches its due date | Assigned intern | Due today |
| An actionable item remains open the next day | Assigned intern | Overdue |
| A personal student-created item is due today | Owning personal student | Due today |
| An unfinished personal item remains open after its due date | Owning personal student | Overdue |
| A personal scheduled workday has no work log by the next morning | Owning personal student | Work hours not logged |

Every item notification carries its `item_type` and exact `item_id`; company notifications also carry the owning `intern_id`. The server generates a fixed internal destination, such as `/company/tasks?intern_id=42&task_id=81` or `/intern/requirements?requirement_id=24`; callers cannot supply redirect URLs.

When a user follows a notification, the page loads the exact target item, scrolls it into view, and highlights it. Company pages select the specified intern first. This must work when the item is not in the current paginated result, so add small read-only detail endpoints if the existing list endpoints cannot resolve it: company item reads are scoped to the selected intern's company-owned internship, and intern item reads are scoped to the authenticated intern's internship. A submitted-proof notification takes the company holder to the exact item with its existing **View proof** action available. Proof remains served only through the existing authorized proof route.

Review comments remain on the item page; notifications do not copy comments, proof files, or private file paths into notification data.

## State and deadline rules

Notification event details are immutable after creation; only `read_at` changes when the recipient reads one. Reading a notification does not change a task, requirement, submission, or review state.

Company-managed task and requirement state remains authoritative in the existing tables:

- A newly assigned item starts with its existing incomplete/to-do state and `not_submitted` submission state.
- An intern submission, including a resubmission after changes, moves the item to `pending_review`. It notifies the company account holder.
- Approval moves the item to `approved` and completes it. It notifies the intern and prevents future deadline reminders.
- A change request sets `changes_requested`. It notifies the intern and keeps the item available for resubmission.

For deadline reminders, an item needs intern action while it is not approved/completed and is not pending company review. At 9:00 a.m. on the due date, create one **Due today** notification if the item needs action. At 9:00 a.m. the following day, create one **Overdue** notification if it remains unfinished/actionable. Changes-requested company items are eligible when actionable at a due-date check. Personal student tasks and requirements use their existing completed status and do not enter company review states. Each deadline reminder is keyed by recipient, item type, item ID, due date, and reminder type.

At 9:00 a.m., the personal missed-hours check looks only at the previous Manila calendar date. It sends one alert when that date is inside the active personal internship period, is included in its configured `work_days`, and has no personal work-log row. A notification key containing the recipient and missed date prevents repeat alerts. A work log added after the alert does not delete history; marking the alert read is the user's action.

If the scheduler misses the due-date run but resumes later that same day, it creates the **Due today** notification then. If it resumes after the due date, it skips the stale **Due today** alert and creates one **Overdue** notification on the next successful run for each still-actionable past-due item that has no overdue notification. This catch-up behavior applies regardless of how many daily runs were missed; it never creates repeated overdue alerts. Pending-review and completed items are skipped. A same-day company assignment or personal student-created item immediately creates its **Due today** notification in the successful creation transaction, using the same deduplication key as the scheduler.

## Data and API design

- Reuse Laravel's database notifications table and the existing `Notifiable` behavior on `User`.
- Add a nullable fixed-length `deduplication_key` column to the notifications table and a unique index on it. Event writers hash the recipient type/ID and stable event identity into this column. Non-event Laravel notifications may leave it null.
- Write event notifications with an atomic insert-or-ignore operation. The database unique constraint is authoritative when requests or scheduler executions race; a duplicate does not fail the underlying successful action.
- Store concise event type, title, message, item type/ID, company intern ID when applicable, and a server-generated internal destination in notification data. Keep proof bytes, proof paths, review comments, passwords, and other secrets out of notification data.
- Use stable event identities: assignment uses item type and ID; proof submission uses item type, item ID, and the timestamp/version saved for that successful submission; review uses item type, item ID, decision, and the saved review timestamp; deadline uses item type, item ID, due date, and reminder type. A resubmission receives a new saved submission timestamp/version.
- Persist the item state change and its event notification in one transaction. Create the notification only after validation and the allowed state transition; a rollback removes both writes.
- Add authenticated endpoints to list the current user's notifications with pagination and unread count, mark one of their notifications as read, and mark all of their notifications as read.
- Add read-only item-detail endpoints for contextual navigation: `GET /company/interns/{intern}/tasks/{task}`, `GET /company/interns/{intern}/requirements/{requirement}`, `GET /intern/tasks/{task}`, and `GET /intern/requirements/{requirement}`. Each returns only authorized item data and never a private proof path.
- The backend returns only a safe notification payload. It validates the requested notification identifier by looking it up through the authenticated user's notification relationship.
- Company notifications are addressed to the account holder derived from the assigned internship's company relationship. Intern notifications are addressed to that internship's intern account. Request parameters never select a recipient or company owner.

## UI behavior

- Add a responsive notification bell to company holder, intern, and personal-student navigation, including mobile layouts.
- The unread count is displayed on the bell with an accessible text label.
- Opening the bell loads the latest notification page and current unread count. The list includes clear empty, loading, and recoverable error states. A request failure shows an error and retry control rather than an empty state; the bell retains its last successful count, or shows that the count is unavailable if none was loaded.
- Unread items have a visible unread indicator in addition to text styling. Selecting one marks it read only after the server confirms, then navigates to the target. If marking it read fails, the UI keeps it unread, shows a recoverable error, and still allows navigation. Mark-all updates the local list/count only after success; on failure the unread state is preserved.
- Direct destinations select the company intern, load the exact item even across pagination, and scroll/highlight it. A missing or inaccessible target produces a recoverable message and a link back to the role-specific list.
- The interface does not claim real-time delivery. New notifications appear on the next bell refresh or app load; no polling, web sockets, or external notification service is added in this slice.
- Personal student work logging, task, requirement, and overview behavior remains unchanged apart from the bell; student alerts link to Work Hours, Tasks, or Requirements.

## Deadline scheduling and local setup

- Add one Laravel scheduled command that finds company-managed tasks and requirements due today and still-actionable past-due items without an overdue notification.
- Run it once daily at 9:00 a.m. in `Asia/Manila`; protect it from overlapping runs and make it safe to repeat. On the next successful execution after downtime, catch up one overdue alert per still-actionable past-due item; do not backfill a stale due-today alert.
- When an assignment is created with a due date equal to today's `Asia/Manila` date, create its due-today notification in the assignment transaction. The scheduler and assignment flow share the same deduplication key.
- Include Laravel's scheduler worker in the local development command so the due-date behavior can be tested locally.
- A deployed environment must run Laravel's scheduler each minute, following Laravel's standard scheduler setup. No queue worker is required for database-only notifications.

## Authorization and data protection

- Only authenticated company account holders, company-managed interns, and personal students can call the notification endpoints.
- Notification list, read-one, and read-all operations are scoped to the authenticated user's own notifications. Cross-user IDs return not found and do not reveal another user's notification content.
- Item-detail reads used for notification navigation are scoped to the authenticated intern or to the selected intern's company-owned internship. A forged item or intern ID cannot expose another account's item.
- Company notification creation derives the company holder from the internship relationship. Intern notification creation derives the recipient from the assigned internship.
- Notification destinations are generated from a fixed internal route map for task or requirement pages and include exact item IDs. Company destinations also include the intern ID so the page selects the correct intern; user-provided strings are not accepted as redirect targets.
- Notification text is escaped by normal Vue interpolation. It contains no raw proof URL or filesystem path.
- Personal student records are never recipients of company-managed events. Personal alerts derive their recipient from the internship's owning student and cannot target a company-managed internship.

## Scope boundaries

This slice includes:

- persistent in-app assignment, proof submission, review, due-today, and overdue notifications;
- unread counts, notification listing, individual read state, and mark-all-read;
- database-enforced notification deduplication and transaction-bound event creation;
- exact item navigation with company intern selection and contextual item highlighting;
- a daily idempotent deadline check using the Philippine timezone, including scheduler catch-up and same-day assignments;
- personal missed-scheduled-workday alerts and due/overdue reminders for unfinished personal tasks and requirements;
- recoverable list and read-action error states;
- company and intern authorization tests and UI coverage.

This slice does not include email, browser push, SMS, real-time sockets, notification preferences, custom notification categories, administrative controls, repeated daily overdue alerts, older missed-hours backfill, assignment edits/deletes, or changes to the existing task and requirement workflows.

## Acceptance criteria

1. Creating a company task or requirement stores one unread assignment notification for that intern, with the exact item type/ID and an internal link.
2. Submitting or resubmitting task or requirement proof creates a notification for the correct company's account holder only after the submission transaction commits; failed or rolled-back submissions create none.
3. Approving an item or requesting changes creates an intern notification only after the review transaction commits; approval completes the item and a change request leaves it resubmittable.
4. Concurrent attempts to create the same event notification produce at most one row because the database unique constraint enforces the recipient-scoped deduplication key.
5. A same-day assignment gets its assignment notification and a due-today notification even if it is created after the daily scheduler scan; the next scheduler run does not duplicate the due-today row.
6. The scheduler creates one due-today notification for an actionable item due today and one overdue notification after its due date. After missed runs it catches up one overdue notification for each still-actionable past-due item, without sending stale due-today alerts or repeated overdue alerts.
7. Deadline notifications are not created for items with no due date, approved/completed items, or items pending company review. Changes-requested items remain eligible.
8. Company notification destinations select the correct intern and exact task/requirement; intern destinations open the exact assigned item. Both locate and highlight the item even when it is outside the first paginated result.
9. Company and intern item-detail reads used by notification navigation enforce the same company/intern ownership checks as their list and proof routes.
10. Company holders and interns can list only their own notifications, see an unread count, mark one notification read, and mark all their notifications as read.
11. Selecting a notification navigates even if marking it read fails, but the UI preserves its unread state and shows a recoverable error. List failures do not appear as an empty list or a zero unread count.
12. A company holder cannot read an intern's notifications, an intern cannot read a company holder's notifications, and one company's holder cannot access another company's notifications.
13. Personal students receive one deduplicated no-hours alert for the previous scheduled workday when no work log exists, plus due-today and one overdue reminder for unfinished tasks and requirements. Creating a personal task or requirement due today after 9 a.m. sends its due-today alert immediately.
14. Personal students can list and mark only their own notifications as read; **Mark all as read** preserves history and does not affect another user's unread state.
15. Personal work-log, task, requirement, and overview behavior remains unchanged apart from notifications. Export Summary is available on both personal-student and company-managed-intern Overviews and exports only the signed-in user's internship.
16. The notification bell and list remain usable at mobile widths and provide loading, empty, unread, read, and error states for each supported role.

## Verification plan

Backend feature tests should verify every event recipient; notifications are absent after failed validation, failed state transitions, and transaction rollback; database-level deduplication under concurrent/repeated event writes; exact item lookup ownership; list pagination and unread counts; read-one/read-all authorization; mark-all history preservation; personal scheduled-workday/no-log detection; and deadline catch-up/idempotency for due, overdue, pending-review, approved, changes-requested, and no-due-date items. Existing personal student task and requirement tests must continue to pass.

Frontend tests should verify the role-specific bell visibility, unread count, loading/empty/error states, read actions including failed API responses, and contextual navigation to the exact item across pagination. Browser verification should cover assignment through notification, intern proof submission, company review, and scheduler catch-up with an isolated test database and controlled application time.

## Known risks

- The local scheduler and deployed Laravel scheduler must be running for on-time due-date notifications. After an outage, the next successful run can create a batch of one-time overdue alerts for still-actionable past-due items.
- In-app notifications are not real-time; the user sees new events after the bell is refreshed or the app is loaded again.
- A notification is a convenience indicator only; the current task/requirement page remains the source of truth for status and review feedback.
