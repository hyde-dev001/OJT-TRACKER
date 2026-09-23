# Phase 3.25 — Student-Only OJT Tracker Plan

## Scope

> ARCHIVED / HISTORICAL: Phase 3.25 is complete. Use the current project
> context and Phase 3.75 hardening plan for further work.

Implement the approved student-only contract in `docs/superpowers/specs/2026-09-22-ojt-phase-3-25-student-only-design.md`. Preserve the existing shared UI components and student URL namespace. Do not implement Phase 4 or a full visual redesign.

## Execution order

1. Rewrite backend feature tests for student ownership, simplified statuses, progress, and removal of coordinator endpoints.
2. Implement the safe schema migration, models, factories, policies, requests, resources, student controllers, routes, auth payload, and student-only seeder.
3. Rewrite frontend service/auth/router tests for one user type, then remove coordinator views/routes/service methods and update the three student screens.
4. Rewrite the browser smoke flow and update README, project context, agent guidance, and role-specific design copy.
5. Run focused tests after each area, then the full backend/frontend suites, build, smoke, diff check, and stale-reference audit.

## Rules

- Use tests as the behavior contract before production changes where practical.
- Never edit applied migrations or run `migrate:fresh`, `db:wipe`, or destructive database commands.
- Do not leave hidden coordinator routes or dead review API methods.
- Keep authorization and server-side duration calculation intact.
