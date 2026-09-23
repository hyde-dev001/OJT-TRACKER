# Phase 4 — Smart OJT Progress & Completion Assistant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a deterministic, student-scoped Overview page that explains OJT progress, required pace, Needs Attention, and completion readiness.

**Architecture:** The authenticated Laravel endpoint loads only the current student internship and delegates all business calculations to one `OjtProgressAssistant` service. Vue fetches the stable response through the existing Axios service, renders the values without recalculating them, and routes authenticated users to `/student/overview`.

**Tech Stack:** Laravel 12, PHP 8.2, Carbon, Laravel feature tests, Vue 3, Vue Router, Pinia, Axios, Tailwind CSS, Vitest, Vue Test Utils, Playwright smoke script.

**Spec:** `docs/superpowers/specs/2026-09-23-phase-4-smart-assistant-design.md`

## Global Constraints

- The backend is authoritative for progress, scheduled-day counts, pace, status, attention, and readiness.
- The endpoint accepts no `student_id`, `user_id`, or `internship_id`; it scopes through the authenticated user's current internship.
- Dates use `Asia/Manila`, inclusive internship boundaries, and ISO weekdays `1` Monday through `7` Sunday.
- Work-hour progress sums only completed `work_logs.rendered_minutes`; the client must not infer totals from time fields.
- Required requirements block readiness; tasks and optional requirements never block readiness.
- Needs Attention returns at most five deterministic items and exposes the omitted count.
- No notifications, AI/ML, holiday API, calendar sync, reports, coordinator/admin flow, analytics, or Phase 5 behavior.
- Reuse existing components, API unwrap behavior, and styling conventions; add no dependency and no migration.
- Preserve the logged-in non-link brand and all existing Work Hours, Tasks, Requirements, profile, date-modal, and dark-mode behavior.
- Do not commit changes; the repository instructions require an explicit user request before committing.

## Review Focus

- A future start date must report `not_started` and count the whole configured period, not today's remaining window; cover in the service boundary test.
- A deadline-passed internship with remaining minutes must report `deadline_passed`, expose zero scheduled days, and add a high-priority attention item; cover in the status/attention test.
- A required requirement without a due date must affect readiness and attention, while an optional requirement and any task must not block readiness; cover in the readiness test.
- Ties at the fifth attention item must sort by priority, due date with null last, and ID; cover in the deterministic cap test.
- A login redirect query must preserve only internal `/student/` paths and reject an external or public target; cover in the redirect test.

---

### Task 1: Implement and test the deterministic assistant service

**Files:**
- Create: `backend/app/Services/OjtProgressAssistant.php`
- Test: `backend/tests/Feature/OjtProgressAssistantTest.php`

**Interfaces:**
- Consumes: `App\Models\Internship`, its `workLogs`, `tasks`, and `requirements` relationships, and an optional `CarbonImmutable` date.
- Produces: `OjtProgressAssistant::build(Internship $internship, ?CarbonImmutable $today = null): array` returning the `internship`, `progress`, `pace`, `attention`, and `completion` keys defined in the spec.

- [x] **Step 1: Write failing service tests for progress and pace**

Create a `RefreshDatabase` feature test that calls the service directly with a fixed Philippine date. Pin the response to authoritative minutes and ceiling division:

```php
public function test_it_builds_progress_and_required_pace_from_completed_minutes(): void
{
    $internship = Internship::factory()->create([
        'required_minutes' => 1_000,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'work_days' => [1, 2, 3, 4, 5],
        'expected_daily_minutes' => 480,
    ]);

    WorkLog::factory()->completed()->create([
        'internship_id' => $internship->id,
        'rendered_minutes' => 601,
        'work_date' => '2026-09-22',
    ]);
    WorkLog::factory()->create([
        'internship_id' => $internship->id,
        'rendered_minutes' => 900,
        'status' => 'draft',
        'work_date' => '2026-09-23',
    ]);

    $overview = app(OjtProgressAssistant::class)->build(
        $internship->refresh(),
        CarbonImmutable::create(2026, 9, 23, 0, 0, 0, 'Asia/Manila'),
    );

    $this->assertSame(601, $overview['progress']['rendered_minutes']);
    $this->assertSame(399, $overview['progress']['remaining_minutes']);
    $this->assertSame(60, $overview['progress']['percentage']);
    $this->assertSame(6, $overview['pace']['remaining_scheduled_days']);
    $this->assertSame(67, $overview['pace']['required_daily_minutes']);
    $this->assertSame('on_track', $overview['pace']['status']);
}
```

Add tests for percentage capping at 100 and zero remaining pace, then add a schedule test that fixes a Wednesday date, uses `[1, 3, 5]`, and asserts the inclusive count from today through the target end date.

- [x] **Step 2: Run the focused tests and confirm the service is missing**

Run:

```powershell
cd backend
php artisan test tests/Feature/OjtProgressAssistantTest.php
```

Expected: FAIL because `App\Services\OjtProgressAssistant` does not exist yet.

- [x] **Step 3: Write failing tests for status, attention, and readiness**

Pin the rule order with separate test cases:

```php
public function test_it_distinguishes_future_start_deadline_and_at_risk_states(): void
{
    $future = Internship::factory()->create([
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'required_minutes' => 4_800,
        'work_days' => [1, 2, 3, 4, 5],
        'expected_daily_minutes' => 480,
    ]);
    $expired = Internship::factory()->create([
        'start_date' => '2026-08-01',
        'end_date' => '2026-09-22',
        'required_minutes' => 4_800,
        'work_days' => [1, 2, 3, 4, 5],
        'expected_daily_minutes' => 480,
    ]);
    $atRisk = Internship::factory()->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-24',
        'required_minutes' => 4_800,
        'work_days' => [1, 2, 3, 4, 5],
        'expected_daily_minutes' => 60,
    ]);

    $today = CarbonImmutable::create(2026, 9, 23, 0, 0, 0, 'Asia/Manila');

    $this->assertSame('not_started', app(OjtProgressAssistant::class)->build($future, $today)['pace']['status']);
    $this->assertSame('deadline_passed', app(OjtProgressAssistant::class)->build($expired, $today)['pace']['status']);
    $this->assertSame('at_risk', app(OjtProgressAssistant::class)->build($atRisk, $today)['pace']['status']);
}
```

Add records for overdue/today/soon tasks and requirements, completed exclusions, a required no-due-date requirement, optional due records, and more than five candidates. Assert priorities `1` through `12`, `additional_count`, due-date/ID tie ordering, and fixed route links. Add readiness cases proving required requirements block, optional requirements/tasks do not, and hours alone can make a student ready when no required requirements exist.

- [x] **Step 4: Implement the smallest service that satisfies the tests**

Implement the service with these private helpers and no generic dashboard abstraction:

```php
final class OjtProgressAssistant
{
    public function build(Internship $internship, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today(config('app.timezone'));
        $progress = $internship->progressSummary();
        $rendered = (int) $progress['completed_minutes'];
        $required = (int) $internship->required_minutes;
        $remaining = max($required - $rendered, 0);
        $scheduledDays = $this->remainingScheduledDays($internship, $today);
        $requiredDaily = $remaining === 0
            ? 0
            : ($scheduledDays > 0 ? (int) ceil($remaining / $scheduledDays) : null);
        $status = $this->status($internship, $today, $remaining, $scheduledDays, $requiredDaily);

        return [
            'internship' => [
                'start_date' => $internship->start_date?->toDateString(),
                'end_date' => $internship->end_date?->toDateString(),
                'work_days' => array_map('intval', $internship->work_days ?? []),
                'expected_daily_minutes' => $internship->expected_daily_minutes,
            ],
            'progress' => [
                'required_minutes' => $required,
                'rendered_minutes' => $rendered,
                'remaining_minutes' => $remaining,
                'percentage' => $required > 0 ? min(100, (int) round($rendered / $required * 100)) : 0,
            ],
            'pace' => [
                'status' => $status,
                'remaining_scheduled_days' => $scheduledDays,
                'required_daily_minutes' => $requiredDaily,
                'expected_daily_minutes' => $internship->expected_daily_minutes,
            ],
            'attention' => $this->attention($internship, $today, $status, $remaining),
            'completion' => $this->completion($internship, $remaining),
        ];
    }
}
```

Implement `remainingScheduledDays()` with an inclusive `CarbonPeriod`/date loop, only configured ISO weekdays, and the future-start/active/expired window rules. Implement `status()` in the exact order from the spec. Implement attention candidates from the scoped relationships, assigning optional priorities `10`, `11`, and `12`, sorting by priority, due date with null last, then ID, and slicing to five. Implement readiness from completed minutes plus `is_required = true` and `status != completed` requirements only.

- [x] **Step 5: Run the focused service tests and inspect the response**

Run:

```powershell
cd backend
php artisan test tests/Feature/OjtProgressAssistantTest.php
```

Expected: PASS for progress, schedule, status, attention, cap, and readiness cases.

### Task 2: Expose the authenticated overview endpoint

**Files:**
- Create: `backend/app/Http/Controllers/Student/OverviewController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/OverviewEndpointTest.php`

**Interfaces:**
- Consumes: `OjtProgressAssistant::build()` and the existing authenticated `student` route group.
- Produces: `GET /api/student/overview` with the service payload wrapped under `data`.

- [x] **Step 1: Write failing endpoint and isolation tests**

Add tests for guest `401`, authenticated own-account `200`, stable response keys, and a second student's records not appearing even when the request includes ignored query parameters:

```php
public function test_student_overview_is_scoped_to_the_authenticated_current_internship(): void
{
    $student = User::factory()->create();
    $other = User::factory()->create();
    $own = Internship::factory()->create(['student_id' => $student->id]);
    Internship::factory()->create(['student_id' => $other->id, 'required_minutes' => 99_999]);

    $this->stateful()->actingAs($student)
        ->getJson('/api/student/overview?student_id='.$other->id.'&internship_id=999999')
        ->assertOk()
        ->assertJsonPath('data.progress.required_minutes', $own->required_minutes)
        ->assertJsonMissingPath('data.internship.student_id')
        ->assertJsonStructure([
            'data.internship' => ['start_date', 'end_date', 'work_days', 'expected_daily_minutes'],
            'data.progress' => ['required_minutes', 'rendered_minutes', 'remaining_minutes', 'percentage'],
            'data.pace' => ['status', 'remaining_scheduled_days', 'required_daily_minutes', 'expected_daily_minutes'],
            'data.attention' => ['items', 'additional_count'],
            'data.completion' => ['ready', 'remaining_minutes', 'incomplete_required_requirements', 'blockers'],
        ]);
}
```

Use an assertion on the actual response that no other internship ID or required-minute value is included; do not rely on a client-provided ID being absent from the request only.

- [x] **Step 2: Run the endpoint tests and confirm the route/controller are missing**

Run:

```powershell
cd backend
php artisan test tests/Feature/OverviewEndpointTest.php
```

Expected: FAIL because the route and controller do not exist.

- [x] **Step 3: Add the thin controller and route**

Register the controller in `backend/routes/api.php` and add the route inside the existing authenticated student group:

```php
Route::get('/overview', OverviewController::class);
```

Use an invokable controller that does only current-internship lookup and response wrapping:

```php
public function __invoke(Request $request, OjtProgressAssistant $assistant): JsonResponse
{
    $internship = $request->user()->currentInternship()->firstOrFail();

    return response()->json([
        'data' => $assistant->build($internship),
    ]);
}
```

- [x] **Step 4: Run the endpoint tests**

Run:

```powershell
cd backend
php artisan test tests/Feature/OverviewEndpointTest.php
```

Expected: PASS for guest protection, current-internship response, and cross-account isolation.

### Task 3: Add the frontend overview service and readable status mapping

**Files:**
- Modify: `frontend/src/services/ojt.js`
- Modify: `frontend/src/services/ojt.test.js`
- Modify: `frontend/src/components/StatusBadge.vue`
- Modify: `frontend/src/components/StatusBadge.test.js`

**Interfaces:**
- Consumes: `GET /student/overview` through the centralized Axios client.
- Produces: `getStudentOverview()` returning the unwrapped overview object and readable labels for all assistant statuses.

- [x] **Step 1: Write the failing service and status-label tests**

Add a service test that mocks the existing API module:

```js
it('loads the authenticated student overview', async () => {
  api.get.mockResolvedValue({ data: { data: { progress: { percentage: 48 } } } })

  await expect(getStudentOverview()).resolves.toEqual({ progress: { percentage: 48 } })
  expect(api.get).toHaveBeenCalledWith('/student/overview')
})
```

Add status tests for `not_started`, `on_track`, `at_risk`, `complete`, and `deadline_passed`, asserting labels such as `On track` and `Deadline passed` instead of raw underscore values.

- [x] **Step 2: Run the focused frontend tests and confirm failure**

Run:

```powershell
cd frontend
npx vitest run src/services/ojt.test.js src/components/StatusBadge.test.js
```

Expected: FAIL because `getStudentOverview()` and the assistant status labels do not exist.

- [x] **Step 3: Implement the service and extend the existing status component**

Add the one-line service wrapper beside the existing OJT service methods:

```js
export const getStudentOverview = () => unwrap(api.get('/student/overview'))
```

Extend `StatusBadge`'s existing `labels` and semantic tone logic without changing task/requirement statuses. Keep `complete` distinct from `completed` so the Overview communicates readiness status clearly.

- [x] **Step 4: Run the focused frontend tests**

Run:

```powershell
cd frontend
npx vitest run src/services/ojt.test.js src/components/StatusBadge.test.js
```

Expected: PASS.

### Task 4: Build the Overview view with loading, error, attention, and readiness states

**Files:**
- Create: `frontend/src/views/student/OverviewView.vue`
- Create: `frontend/src/views/student/OverviewView.test.js`

**Interfaces:**
- Consumes: `getStudentOverview()`, `ProgressBar`, `StatusBadge`, `PageHeader`, `EmptyState`, and existing formatter/API error helpers.
- Produces: An accessible read-only Overview page with stable test IDs and links to `/student/work-hours`, `/student/tasks`, and `/student/requirements`.

- [x] **Step 1: Write failing view tests for the primary success state**

Mock `getStudentOverview()` with a complete response containing progress, pace, one attention item, and readiness blockers. Assert the page renders backend values and action links without recomputing them:

```js
it('renders backend progress, pace, attention, and readiness data', async () => {
  ojt.getStudentOverview.mockResolvedValue({
    internship: { start_date: '2026-09-01', end_date: '2026-12-15' },
    progress: { required_minutes: 30000, rendered_minutes: 14400, remaining_minutes: 15600, percentage: 48 },
    pace: { status: 'on_track', remaining_scheduled_days: 38, required_daily_minutes: 411, expected_daily_minutes: 480 },
    attention: { items: [{ type: 'requirement', id: 7, title: 'Medical clearance', due_date: '2026-09-23', reason: 'due_today', priority: 5, href: '/student/requirements' }], additional_count: 0 },
    completion: { ready: false, remaining_minutes: 15600, incomplete_required_requirements: [{ id: 7, title: 'Medical clearance', due_date: '2026-09-23' }], blockers: ['hours_remaining', 'required_requirements_incomplete'] },
  })

  const wrapper = mount(OverviewView, {
    global: {
      stubs: {
        RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
      },
    },
  })
  await flushPromises()

  expect(wrapper.get('[data-testid="overview-progress"]').text()).toContain('48%')
  expect(wrapper.get('[data-testid="overview-status"]').text()).toContain('On track')
  expect(wrapper.get('[data-testid="overview-attention-item"]').text()).toContain('Medical clearance')
  expect(wrapper.get('[data-testid="overview-attention-item"] a').attributes('href')).toBe('/student/requirements')
  expect(wrapper.get('[data-testid="overview-completion"]').text()).toContain('15600')
})
```

Use the repository's existing test stubbing pattern rather than adding a router package or test dependency. Add tests for ready copy, empty attention, loading, safe error text plus retry, and no-internship empty state.

- [x] **Step 2: Run the focused view tests and confirm failure**

Run:

```powershell
cd frontend
npx vitest run src/views/student/OverviewView.test.js
```

Expected: FAIL because `OverviewView.vue` does not exist.

- [x] **Step 3: Implement the view with one request and existing UI primitives**

Use a single `load()` function and keep all calculations out of the component. Format minutes only for display with the existing `formatDuration` helper. Render the hierarchy in this order:

```vue
<PageHeader title="Overview" description="See your OJT progress, pace, and next steps at a glance." />
<section data-testid="overview-progress">...</section>
<section data-testid="overview-pace">...</section>
<section data-testid="overview-attention">...</section>
<section data-testid="overview-completion">...</section>
```

Use `RouterLink` for attention items, `ActionAlert` only where an existing interaction requires it, and `role="status"`/`role="alert"` for loading and errors. Show safe copy such as “Your tracked OJT data is ready to complete” and never state that a school or company has officially approved completion.

- [x] **Step 4: Run the focused view tests**

Run:

```powershell
cd frontend
npx vitest run src/views/student/OverviewView.test.js
```

Expected: PASS.

### Task 5: Make Overview the authenticated landing route and update navigation

**Files:**
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/App.vue`
- Modify: `frontend/src/views/LoginView.vue`
- Modify: `frontend/src/views/RegisterView.vue`
- Modify: `frontend/src/router/index.test.js`
- Modify: `frontend/src/App.test.js`
- Modify: `frontend/src/views/LoginView.test.js`
- Modify: `frontend/src/views/RegisterView.test.js`

**Interfaces:**
- Consumes: the new named route `student-overview` and the existing `redirect` query created by the auth guard.
- Produces: default authenticated navigation to `/student/overview`, safe internal deep-link restoration after login, and registration redirect to Overview.

- [x] **Step 1: Update failing route/navigation assertions**

Change router tests to expect `student-overview` when an authenticated user visits `/login`; add a route-protection test for `/student/overview`. Update App tests to expect Overview in the authenticated nav and keep the brand as a `DIV` without an `href`.

Add a hoisted `route` mock returned by `useRoute()` and test the redirect rule:

```js
it('uses an internal student redirect and otherwise enters Overview', async () => {
  route.query = { redirect: '/student/tasks' }
  api.post.mockResolvedValue({ data: { user: { id: 1, name: 'Student', email: 'student@example.com' } } })
  const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } })
  await wrapper.get('input[type="email"]').setValue('student@example.com')
  await wrapper.get('input[type="password"]').setValue('StrongPassword1!')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(routerPush).toHaveBeenCalledWith('/student/tasks')

  routerPush.mockReset()
  route.query = { redirect: 'https://evil.example' }
  const secondWrapper = mount(LoginView, { global: { plugins: [createPinia()] } })
  await secondWrapper.get('input[type="email"]').setValue('student@example.com')
  await secondWrapper.get('input[type="password"]').setValue('StrongPassword1!')
  await secondWrapper.get('form').trigger('submit')
  await flushPromises()
  expect(routerPush).toHaveBeenCalledWith('/student/overview')
})
```

Update register and smoke-oriented view assertions from `/student/work-hours` to `/student/overview`.

- [x] **Step 2: Run the affected frontend tests and confirm failure**

Run:

```powershell
cd frontend
npx vitest run src/router/index.test.js src/App.test.js src/views/LoginView.test.js src/views/RegisterView.test.js
```

Expected: FAIL against the current Work Hours redirect/nav.

- [x] **Step 3: Add the route, nav link, and safe redirect handling**

Register the protected route before the other student pages:

```js
{
  path: '/student/overview',
  name: 'student-overview',
  component: OverviewView,
  meta: { requiresAuth: true },
}
```

Change authenticated guards to return `/student/overview`. In LoginView, accept a redirect only when it is a string beginning with `/student/`; otherwise use `/student/overview`. Change RegisterView to push `/student/overview` after successful registration. Add Overview as the first authenticated `RouterLink` in `App.vue` and retain the current account/theme controls.

- [x] **Step 4: Run the affected frontend tests**

Run:

```powershell
cd frontend
npx vitest run src/router/index.test.js src/App.test.js src/views/LoginView.test.js src/views/RegisterView.test.js
```

Expected: PASS.

### Task 6: Extend browser smoke coverage for the Overview workflow

**Files:**
- Modify: `scripts/phase0_smoke.py`

**Interfaces:**
- Consumes: the running Laravel API, Vite frontend, seeded demo account, and existing Playwright helpers.
- Produces: a browser-level check that authentication, Overview rendering, navigation, and the existing student workflow still function together.

- [x] **Step 1: Update login and registration smoke helpers**

Change the login helper and registration flow to wait for `/student/overview`, assert the Overview heading and `overview-progress`, and assert the authenticated nav includes Overview, Work Hours, Tasks, and Requirements. Keep the existing checks for no coordinator links, account menu, theme toggle, mobile width, date modals, and brand non-navigation.

- [x] **Step 2: Add Overview state assertions around mutations**

After the smoke work log is saved, navigate to Overview and assert `overview-progress`, `overview-status`, and `overview-completion` are visible and contain the rendered-hours, pace, and readiness sections. After creating/completing the smoke task and requirement, revisit Overview and assert the page reloads without an error and the attention section is either an empty state or a list of linked items; the detail pages remain responsible for mutation assertions.

- [x] **Step 3: Run the browser smoke command**

Run from the repository root with the local servers:

```powershell
python .agents/skills/webapp-testing/scripts/with_server.py `
  --server "cd backend && php artisan serve --host=127.0.0.1 --port=8000" --port 8000 `
  --server "cd frontend && npm.cmd run dev -- --host=127.0.0.1 --port=5173" --port 5173 `
  -- python scripts/phase0_smoke.py
```

Expected: the script exits zero and prints the existing smoke success line updated to mention Overview coverage.

### Task 7: Update durable project documentation and run the full verification set

**Files:**
- Modify: `PROJECT_CONTEXT.md`
- Modify: `DESIGN.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: the implemented endpoint, Overview route, deterministic calculation rules, and safe completion wording.
- Produces: documentation that no longer describes Phase 4 as future work and does not introduce Phase 5 scope.

- [ ] **Step 1: Update product context**

In `PROJECT_CONTEXT.md`, move the Progress & Needs-Attention Assistant into the implemented core feature list. Replace the “future Phase 4” and “do not implement” language with the exact deterministic rules: backend-authoritative minutes, inclusive configured workdays, pace statuses, five-item attention cap, and required-requirement readiness.

- [ ] **Step 2: Update the design reference**

In root `DESIGN.md`, document the Overview hierarchy as Progress → Pace → Needs Attention → Completion Readiness, the semantic status labels, loading/error/empty states, responsive/dark-mode behavior, and the rule that readiness is not an official school/company completion decision.

- [ ] **Step 3: Update setup documentation**

In `README.md`, add `/student/overview` to the authenticated route description, describe the Overview response behavior in plain language, and remove only statements that say Phase 4 is not implemented. Keep the existing local setup and Philippine timezone instructions unchanged.

- [ ] **Step 4: Run focused backend and frontend checks**

Run:

```powershell
cd backend
php artisan test tests/Feature/OjtProgressAssistantTest.php tests/Feature/OverviewEndpointTest.php
cd ..\frontend
npm.cmd run test -- --run
npm.cmd run build
```

Expected: all focused tests pass and Vite produces a successful production build.

- [ ] **Step 5: Run the full verification set**

Run:

```powershell
cd backend
php artisan test
cd ..\frontend
npm.cmd run test -- --run
npm.cmd run build
cd ..
git diff --check
```

Then rerun the browser smoke command from Task 6. Record exact counts and any non-failing warnings; do not claim completion until all relevant commands have fresh results.

## Handoff

This plan is intended for the previously selected `executing-plans` workflow. After plan approval, execute the tasks sequentially, running each focused check before moving to the next task. Do not commit unless the user explicitly requests it.
