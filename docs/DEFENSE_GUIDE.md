# OJT Progress Tracker Defense Guide

## One-sentence explanation

OJT Progress Tracker is a student-only personal workspace that records
rendered internship hours, organizes tasks and requirements, and explains
whether the student's tracked progress is on pace for the configured target.

## System flow

```text
Register
  -> Account Setup
  -> OJT Setup
  -> Overview
  -> Work Hours
  -> Tasks
  -> Requirements
  -> deterministic Smart OJT Progress & Completion Assistant updates
```

Overview does not duplicate the detail pages. Work Hours stores daily records;
Overview interprets the saved hours, schedule, deadline, tasks, and
requirements into pace, attention, and readiness guidance.

## Feature explanations

### 1. Registration and OJT setup

**Problem:** Student accounts and internship schedules are often tracked
separately, making progress calculations unreliable.

**Solution:** A two-step Vue form collects account data first, checks email
availability, then collects required hours, dates, recurring work days, and
expected daily hours. Laravel validates both steps and creates the user and
internship atomically.

**Validation:** Passwords require 12 characters, uppercase, lowercase, number,
and symbol. The target end date must follow the start date, at least one work
day is required, and expected daily hours are stored as integer minutes.

### 2. Work Hours

**Problem:** Manually totaling daily logs makes remaining hours and progress
easy to get wrong.

**Solution:** The student chooses a valid OJT work date, time in, time out,
break, and optional accomplishment. Laravel calculates rendered minutes and
the log counts immediately. The page provides progress, editing, deletion,
date validation, and pagination.

**Backend:** Work logs are scoped to the authenticated internship; duplicate
dates, future dates, off-duty dates, invalid times, and out-of-period dates are
rejected server-side.

### 3. Tasks

**Problem:** Personal OJT action items disappear in notes or spreadsheets.

**Solution:** Tasks have a simple student workflow: To Do, In Progress, and
Completed. Due dates, overdue labels, filters, pagination, and success alerts
make the next action visible.

### 4. Requirements

**Problem:** Internship checklist items and completion notes are easy to lose.

**Solution:** Requirements can be Required or Optional, can carry notes and due
dates, and move between Incomplete and Completed. Completed requirements must be
reopened before they can be edited or deleted. The Overdue filter shows only
incomplete items whose due date has passed in the Philippine timezone.

### 5. Smart OJT Progress & Completion Assistant

**Problem:** Traditional OJT tracking requires students to manually total their
hours, check deadlines individually, and estimate whether they can finish on
time.

**Solution:** The deterministic assistant calculates remaining hours and
required work pace, prioritizes items needing attention, and identifies what
still prevents the student's tracked OJT requirements from being ready.

**Backend:** Laravel owns minutes, schedule-day counting, pace status,
attention priority, and readiness. Vue formats the values and links each item
to the relevant detail page. The system uses no AI/ML.

## Innovation statement

Traditional OJT tracking requires students to manually total their hours,
check deadlines individually, and estimate whether they can complete their
required hours before their target end date.

The OJT Progress Tracker improves that process through a deterministic Smart
OJT Progress & Completion Assistant that automatically calculates remaining
hours and required work pace, prioritizes items that need attention, and
identifies what still prevents the student's tracked OJT requirements from
being complete.

## Overview versus Work Hours

Work Hours is the detailed record-management page where the student logs and
manages daily rendered hours.

Overview is the decision-support page. It combines the student's hour
progress, configured schedule, tasks, requirements, and target deadline to
explain whether the student is on track and what needs attention next.

## Suggested 5–7 minute demo

1. Open Home and briefly explain the three core areas.
2. Sign in with `student@example.com` / `OjtTracker!2026`.
3. On Overview, explain rendered progress, Required Pace, Needs Attention, and
   Completion Readiness.
4. Open Work Hours, add one valid work log, and show the saved-success alert.
5. Return to Overview and point out the updated progress.
6. Open Tasks and show To Do → In Progress → Completed.
7. Open Requirements and show Required/Optional, completion, and Overdue
   filtering.
8. Return to Overview to show the assistant's updated interpretation.
9. If time permits, toggle dark mode and refresh to show persistence.

## Common questions

**What makes this innovative?**
It converts separate logs, schedule settings, deadlines, and requirements into
explainable pace, attention, and readiness guidance instead of only displaying
records.

**Why not just use Excel?**
The application validates dates and times, calculates duration on the server,
scopes records to the signed-in student, and derives pace/readiness without
manual formulas.

**Why is there an Overview if Work Hours exists?**
Work Hours is detailed record management; Overview interprets those records and
shows what to do next.

**How is On Track calculated?**
The backend compares rendered minutes with the required minutes remaining and
the number of remaining configured OJT weekdays through the target date.

**How is At Risk calculated?**
When required daily pace is higher than the student's expected daily schedule,
the backend reports At Risk. Missing schedule inputs produce Pace Unavailable,
not At Risk.

**Why do tasks not block readiness?**
Tasks are personal productivity items. Readiness only represents tracked OJT
hours and required internship requirements.

**Why do required requirements block readiness?**
They are explicit checklist items the student marked as required during OJT
setup/workflow; optional items remain informative.

**Why are holidays not automatically excluded?**
The two-week project uses recurring configured weekdays and avoids an external
holiday dependency. This is a documented scope limitation.

**How do you prevent another student from seeing data?**
Laravel authenticates the student, scopes queries through the current
internship, and applies authorization policies to record access and mutation.

**Why is Laravel authoritative?**
The browser is untrusted. Laravel validates inputs, calculates rendered
minutes, enforces transitions, and persists the result consistently.

**What happens if the student exceeds required hours?**
Progress is capped at 100%, while the saved work logs remain available for the
student's record.

**What happens after the target date?**
Remaining tracked hours produce a Deadline Passed pace/attention state. The
backend still preserves historical records and enforces date rules.

**Does the application certify official OJT completion?**
No. It is a personal tracker and does not replace school or company
verification.

**What happens if the OJT start date is in the future?**
The setup is valid, but Work Hours disables logging until the start date and
the backend rejects premature work dates.

**How are work-log dates validated?**
The date must be within the configured OJT period, no later than today in
`Asia/Manila`, match a configured weekday, and be unique per internship.

## Deliberate limitations

- Personal tracker only; no official company/school approval.
- No automatic holiday exclusion.
- No push, email, or SMS notifications.
- No coordinator portal or multi-student dashboard.
- No AI prediction; the assistant is deterministic and explainable.
- Schedule uses recurring selected weekdays.
- Current-day capacity is treated as a whole configured OJT day.

These are deliberate scope choices for a focused student project, not hidden
claims about official attendance or completion.
