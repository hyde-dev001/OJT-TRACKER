# OJT Progress Tracker — Presentation Guide

Use this as a plain-language speaking guide. Replace bracketed text with your
actual demo details, and only claim behavior you have personally verified.

> **About the five steps:** The rubric screenshots require a live demo using a
> five-step flow, but do not show the instructor's official steps. The sequence
> below is a suggested flow based on the rubric, not a quote from the assignment.
> If you have the official flow, use it instead.

## What the final defense is scoring

| Area | Points | What to prove |
| --- | ---: | --- |
| System completeness and functionality | 20 | The main student workflow works end to end, saves data, responds to errors, and is usable on mobile. |
| Innovation and real-world usefulness | 15 | The tracker solves a real student problem beyond generic login and record management. |
| Presentation, defense, and ownership | 15 | You can walk through the app, explain how data moves, show testing evidence, and answer questions about your own system. |

The rubric says the final defense is worth 50 points and is also the project
grade. A polished speech cannot replace a working demo or evidence that the
presenters understand the project.

## Opening — say this in your own voice

> “Our project is the OJT Progress Tracker. It is for a student intern who wants
> to know how many OJT hours are recorded, whether their pace matches their
> schedule, and which tasks or requirements need attention. It is a personal
> tracker; it does not certify official attendance or internship completion.”

Then name the useful improvement:

> “Instead of only storing entries, the app uses the student’s saved hours,
> target, and work schedule to summarize progress and highlight what may need
> attention next.”

Sa Filipino: ipaliwanag ang problema, ang gagamit, at ang praktikal na tulong ng
system. Huwag sabihing AI ang assistant; ang progress at status nito ay
kinakalkula ayon sa nakatakdang rules ng app.

## System capabilities and rules

- **Account and OJT setup:** A student creates an account and configures the
  required OJT hours, start and target dates, work days, and expected hours per
  day.
- **Work hours:** Students save, edit, and delete their own work logs. The
  backend calculates rendered minutes from time-in, time-out, and break; the
  date must be on or after the OJT start date, no later than today, and no
  later than the target end date. Future-dated logs are rejected, and logging
  is unavailable before the OJT start date.
- **Tasks:** Students manage personal tasks with To Do, In Progress, and
  Completed statuses. Tasks do not block completion readiness.
- **Requirements:** Students manage required or optional requirements and can
  complete or reopen them. Only incomplete required requirements affect
  completion readiness.
- **Overview:** Shows recorded-hour progress, schedule-based pace, Needs
  Attention, and completion readiness. Readiness follows the configured
  required-hours and required-requirements rules; it is not official school or
  company verification.
- **Progress summary:** If `Export Summary` is included in the build being
  presented, it downloads a personal progress snapshot, not proof of attendance
  or official completion.

## Suggested five-step live demo

### 1. Identify the problem and the user

**Show:** the app name and the student-facing purpose.

**Say:** “Our only application user is the student intern. The app helps that
student keep personal OJT progress and related items in one place.”

**Prove:** explain why hour totals, dates, and requirements are useful together.
Do not describe the product as a school, supervisor, or attendance-management
system.

### 2. Show the student’s OJT setup

**Show:** a prepared demo account’s OJT hours, start and target dates, work
days, and expected hours per day. Avoid spending live-demo time registering a
new account unless registration itself is being tested.

**Say:** “These settings describe the student’s own internship schedule. The
app uses them when it explains pace and remaining progress.”

**Prove:** use a demo account with safe sample data. Never show real passwords,
API keys, or another student's records.

### 3. Record work hours and prove persistence

**Show:** a saved work log, or add one using valid demo data. Point out the
entered times and break, then show the saved rendered duration. Refresh the
page or revisit the list to show that the record remains saved.

**Say:** “The browser sends the time details to Laravel. The backend validates
them, calculates the rendered minutes, and saves the record. The browser does
not get to choose the official duration.”

If asked about invalid input, show the tested error behavior or the matching
QA/test evidence. Do not create invalid data in the production database.

### 4. Show tasks, requirements, and the Overview

**Show:** one personal task, one required requirement, and the Overview's
progress, pace, Needs Attention, and completion readiness. Use existing sample
records so the status and summary are meaningful.

**Say:** “Tasks help with personal planning. Completion readiness is based on
the required OJT hours and required internship requirements; tasks and optional
requirements do not block it.”

If `Export Summary` is present in the build being defended, download it and
point out that it is a personal progress snapshot, not official verification.

### 5. Prove testing and explain the system

Open [`docs/QA/phase-5-qa.md`](QA/phase-5-qa.md) and show a relevant workflow
or security check. It records a QA run dated **2026-09-23**; because the project
has changed since then, rerun the relevant tests and refresh the evidence for
the exact build you will present—especially for any newer feature such as the
summary export. Report actual outcomes, not expected outcomes.

**Say:** “The screens are built with Vue. When a student saves something, Vue
sends a request to the Laravel API. Laravel checks the session, validates the
input, limits access to that student's internship, applies the business rules,
and saves it in the database. Laravel sends the result back for Vue to display.”

## Questions to rehearse

**What makes this innovative, beyond CRUD?**

“It connects saved hours and the student's schedule to a clear progress and
pace summary, then highlights actionable items. The value is helping the
student understand what to do next—not merely storing records.”

**How do you calculate hours?**

“The Laravel backend calculates duration from the submitted time-in, time-out,
and break, validates the values, and stores the result as minutes.”

**How do you protect student data?**

“The student signs in with a session, and backend queries and updates are
scoped to that student's internship. Hiding a button in the interface is not
the security boundary.”

**Does “Ready” mean the school approved completion?**

“No. It only means the app's configured hour and required-requirement
conditions are met. It is not an attendance certificate or school/company
verification.”

**Did every test pass?**

Answer from the latest test run. Say which command or scenario you ran, what
passed, and what remains unverified. Never present the old QA log as proof for
code changes made after its recorded run.

## Before presenting

- Run the app and API on the exact environment you will use; check the browser
  console and network requests before the defense.
- Sign in to a prepared demo account and confirm it has useful sample data.
- Rehearse the five steps once, including refresh/persistence and one tested
  error or edge case.
- Open the current QA log and latest test results. Keep a backup screenshot or
  short recording in case the live network fails.
- Make sure every presenter can explain the user problem, one feature, and the
  Vue-to-Laravel-to-database flow.
- Be honest about limitations. Do not call the tracker an official attendance
  system or claim tests that were not run on the presented build.
