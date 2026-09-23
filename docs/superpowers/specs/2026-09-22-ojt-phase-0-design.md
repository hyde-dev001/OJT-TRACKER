# OJT Progress Tracker Phase 0 Design

## Goal

Create a small, verifiable technical foundation for the OJT Progress Tracker without implementing any student, coordinator, work-hour, task, requirement, or progress business modules.

## Approved approach

Use two plainly separated applications:

```text
Vue 3 + Vite + Tailwind CSS
        |
      Axios
        |
Laravel REST API
        |
      MySQL
```

The frontend owns the starter UI and client-side routing. Laravel owns the `/api/health` endpoint, environment-backed database configuration, CORS configuration, and the backend test. No Blade or Inertia UI will be used; the generated default Blade welcome route will be removed from `routes/web.php`.

## Alternatives considered

1. **Separate Vue and Laravel applications — selected.** This is the frozen project architecture, demonstrates the required stack directly, and keeps the frontend/backend boundary clear.
2. **Laravel Blade monolith — rejected.** It would not demonstrate Vue as the primary frontend application.
3. **Docker or a larger service scaffold — rejected.** It adds setup cost without helping the Phase 0 workflow and is unnecessary for the local XAMPP environment.

## Phase 0 components

- Backend: one API health route, MySQL configuration, CORS, and one meaningful API test.
- Frontend: Home, About, and 404 routes; a tiny Pinia app store; one centralized Axios client; and a Home health-check state machine with loading, connected, and unavailable states. The About page will use the approved project description and will mention only automatic progress and needs-attention monitoring as the planned improvement.
- Styling: the current Tailwind/Vite integration for the installed Tailwind version, with a responsive starter page only.
- Documentation: root README with actual versions, setup commands, URLs, debugging notes, and explicit Phase 0 boundaries.
- Verification: explicit PHP/Composer/Node/npm/Git/MySQL preflight checks, backend tests, frontend tests, production build, migration/connection check when MySQL is reachable, and a browser smoke check of the real API request.

## Error handling

The Home view treats network failures, timeouts, and non-success responses as the same safe user-facing unavailable state. Raw exception details stay out of the UI. Laravel uses its normal development error reporting and test assertions.

## Scope guard

Do not add authentication, roles, profiles, work logs, tasks, requirements, calculations, notifications, reports, uploads, or future feature folders during this phase.
