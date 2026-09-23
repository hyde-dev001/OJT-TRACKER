# AGENTS.md

# OJT Progress Tracker — AI Development Operating Model

## Project context

This repository is a focused two-week student web application project.

Before substantial work, read:

1. `PROJECT_CONTEXT.md`
2. `DESIGN.md`
3. relevant source files and nearby tests

The expected architecture is:

- Vue 3 + Vite frontend
- JavaScript
- Vue Router
- Pinia
- Axios
- Tailwind CSS
- Laravel REST API backend
- MySQL persistence

Do not assume the SoleSpace architecture. This project does **not** use React, TypeScript, Inertia, ERP modules, payments, logistics, or multi-tenant shop workflows unless the user explicitly changes scope.

---

## Product guardrail

The project must remain focused on one student-intern monitoring workflow.

Primary target user:
- Student Intern

Supporting actor:
- None. The student is the sole application user.

Frozen core features:
1. Work-Hour Progress Tracking
2. Task & Requirement Monitoring
3. Progress & Needs-Attention Assistant

Do not expand the system into a school management system, LMS, HR system, payroll system, or generic administration suite.

When a request appears to expand scope, compare it with `PROJECT_CONTEXT.md` before implementing.

---

## Default operating mode

- Use one main agent and execute changes sequentially.
- Prefer the minimum coherent implementation.
- Do not create speculative architecture for future features.
- Preserve unrelated working-tree changes.
- Never reset, discard, or overwrite unrelated user work.
- Do not commit, push, or rewrite Git history unless explicitly requested.
- Do not edit real `.env` credentials unless explicitly authorized.
- Do not run destructive database commands without explicit approval.

Roles such as Backend, Frontend, Design, QA, Security, and Writer are responsibilities of the current agent, not mandatory separate agents.

---

## Workflow

### Step 1 — Triage

Identify:

- requested user outcome;
- whether the request belongs to the frozen scope;
- affected frontend/backend/database areas;
- authorization or data-integrity risk;
- likely files;
- relevant tests;
- relevant skills.

Inspect:

```bash
git status
```

Preserve unrelated changes.

For UI-facing work, read `DESIGN.md` before editing.

### Step 2 — Short implementation contract

For non-trivial work, state or internally establish:

- acceptance criteria;
- files/areas likely to change;
- business rules;
- risks;
- narrow verification commands.

Ask for clarification only when a missing decision materially changes the result.

### Step 3 — Explore before editing

Inspect the existing implementation and tests.

Prefer repository conventions over introducing new patterns.

Do not create:
- duplicate API clients;
- duplicate status components;
- duplicate validation conventions;
- duplicate progress calculations.

### Step 4 — Implement sequentially

1. Make one coherent change.
2. Run the narrowest relevant check.
3. Read the result.
4. Fix failures.
5. Continue.

Do not batch large unrelated changes.

### Step 5 — Review

Review the change for:

1. project scope;
2. acceptance criteria;
3. readability and unnecessary complexity;
4. authorization/security;
5. validation;
6. data integrity;
7. responsive UI and accessibility when applicable;
8. regression risk;
9. test coverage.

### Step 6 — Verify before completion

Never claim completion without fresh evidence.

Report exact commands run and their outcomes.

---

## Architecture rules

### Frontend

Use:

- Vue 3 Composition API where it improves clarity;
- Vue Router for route navigation;
- Pinia only for genuinely shared state;
- Axios through the centralized API client;
- Tailwind CSS for styling.

Do not:

- introduce React;
- introduce Inertia;
- introduce Bootstrap/Vuetify/PrimeVue without explicit approval;
- store everything globally in Pinia;
- put backend business rules only in Vue;
- hardcode the API base URL across components.

Prefer:

```text
views → components → services/api → Laravel API
```

Keep components focused and reusable where real reuse exists.

### Backend

Use Laravel conventions.

Prefer:

- Form Requests or clear request validation for non-trivial inputs;
- policies/middleware for authorization;
- Eloquent relationships;
- transactions only when atomic multi-write behavior requires them;
- API Resources when they improve response consistency;
- service/action classes only when logic has enough complexity to justify them.

Do not create empty service layers or repositories just for architecture aesthetics.

### Database

- Use migrations.
- Use foreign keys where appropriate.
- Use indexes for real query needs.
- Store work duration as integer minutes.
- Keep schema terminology aligned with product terminology.
- Do not use destructive migration/database commands without approval.

---

## Business-rule guardrails

### Work-hour calculations

The backend is authoritative.

Do not trust client-calculated total hours.

Validate:

- date/time presence;
- time-out after time-in where applicable;
- valid break duration;
- no negative duration.

Prefer integer minute storage.

### Internship schedule and dates

- Registration requires a target end date after the required OJT start date.
- Registration stores recurring work days as unique ISO weekdays 1–7 and
  expected hours per day as backend-derived integer minutes.
- Do not assume Monday–Friday, eight hours, or a fixed calendar when future
  Phase 4 pace logic is designed; use the configured schedule.
- Future start dates remain valid. The UI must block work logging before the
  start date, and the backend must reject dates before start, after today, or
  after target end.
- Phase 4 does not automatically exclude public holidays unless an approved
  product decision changes that rule.

### Progress

Work-hour progress and requirement progress are separate concepts.

Do not create an arbitrary combined "overall score".

### Needs Attention

Derived states should be deterministic and explainable.

Examples:

- overdue requirement;
- due-soon task;
- incomplete required item;
- remaining OJT hours;
- completion readiness.

Do not introduce AI/ML just to implement the assistant.

### Statuses

Use explicit readable workflow states when behavior differs by state.

Do not collapse important workflows into ambiguous booleans.

Tasks are personal productivity records and do not block completion readiness;
do not add a task-required flag. Requirements use incomplete and completed
states; completed requirements reject direct edit/delete and are changed
through the existing reopen action.

---

## Design rules

The root design reference is OJT-specific. The preserved raw Cal-inspired
material lives at docs/design-reference/CAL_DESIGN.md and must not override
PROJECT_CONTEXT.md or the OJT-specific design rules.

`DESIGN.md` is the authoritative design reference.

For UI changes:

- use neutral/light product styling;
- use black/near-black primary actions;
- reserve semantic colors for state;
- show text labels with status colors;
- prioritize OJT progress and Needs Attention;
- implement loading/empty/error/success states;
- ensure mobile responsiveness;
- avoid decorative charts and card overload.

Do not copy scheduling-specific Cal.com UI.

---

## Validation and feedback

All user input must be validated.

Frontend validation improves UX but does not replace Laravel validation.

The UI should provide useful:

- loading states;
- success feedback;
- field validation errors;
- empty states;
- recoverable system errors.

Do not expose internal exception messages or stack traces in the production-facing UI.

---

## Security rules

Apply extra security review when changing:

- authentication;
- authorization;
- file uploads;
- API endpoints;
- user-controlled input;
- sensitive profile data;
- secrets/configuration.

Minimum expectations:

- server-side authorization;
- server-side validation;
- safe mass assignment;
- no hardcoded secrets;
- scoped access to student-owned records;
- appropriate CORS;
- no debug information exposed to users.

---

## Testing strategy

Use the narrowest relevant test first.

### Laravel

Prefer Feature tests for API/user workflows.

Important future tests include:

- student can access only their own internship records;
- invalid work times are rejected;
- backend calculates rendered duration correctly;
- status transition rules are enforced;
- overdue/incomplete states are derived correctly;
- completion readiness is correct.

### Vue

Use Vitest + Vue Test Utils for component/view logic where useful.

Test behaviors rather than implementation details.

Examples:

- progress values render correctly;
- validation feedback appears;
- empty state appears;
- status badge displays readable status;
- API failure state does not crash the page.

### Browser verification

Use browser/E2E verification for important complete workflows when available.

UI-facing work is not considered fully verified by unit tests alone when browser behavior can realistically be checked.

---

## Skill layer

Use only skills relevant to the current task.

### Planning / disciplined execution

Recommended:
- `superpowers:brainstorming`
- `superpowers:writing-plans`
- `superpowers:systematic-debugging`
- `superpowers:test-driven-development`
- `superpowers:verification-before-completion`

Do not force every skill into every task.

### Laravel

Use:
- `laravel-best-practices`

Apply it when writing or reviewing:
- controllers;
- routes;
- models;
- migrations;
- validation;
- policies;
- Eloquent queries;
- tests.

### UI/UX

Use:
- `ui-ux-pro-max`

Apply it for:
- page structure;
- forms;
- navigation;
- responsive layout;
- accessibility;
- information hierarchy.

`DESIGN.md` remains project-authoritative if a generic skill conflicts with project-specific design decisions.

### Browser QA

Use:
- `webapp-testing`

Apply it when a runnable user-facing flow needs browser verification.

### Security

Use:
- `security-review`

Apply it for auth, authorization, API input, uploads, secrets, and sensitive data.

---

## Avoid unnecessary skill/process overhead

This is a small two-week project.

Do not require:
- parallel review teams;
- multi-agent orchestration;
- React-specific skills;
- TypeScript-specific skills;
- code-splitting review for trivial pages;
- enterprise architecture review;
- speculative performance work.

Prefer finishing the required end-to-end workflow over adding process complexity.

---

## Verification commands

Use actual scripts from the repository. Do not claim commands exist until `package.json` or `composer.json` confirms them.

Typical frontend commands may include:

```bash
cd frontend
npm run test
npm run build
```

Typical Laravel checks may include:

```bash
cd backend
php artisan test
```

Always include:

```bash
git diff --check
```

when practical before completion.

If linting/type-checking is not configured, report it as unavailable instead of pretending it passed.

---

## Documentation

Update documentation when setup, workflow, or architecture changes.

Primary durable files:

- `PROJECT_CONTEXT.md` — product truth and scope
- `DESIGN.md` — UI/UX truth
- `AGENTS.md` — AI working rules
- `README.md` — human setup/run instructions

Do not use documentation as a dumping ground for temporary debugging notes.

---

## Completion report

When finishing a coding task, report:

1. what changed;
2. why;
3. important files;
4. tests/checks run;
5. exact results;
6. anything not verified;
7. any scope or follow-up concern.

Never say "done", "fixed", or "working" without fresh relevant verification.
