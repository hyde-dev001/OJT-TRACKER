# OJT Progress Summary Export Implementation Plan

> Implement task-by-task with one main agent, following `AGENTS.md`. Checkboxes track progress.

**Goal:** Let a signed-in student download a concise PDF snapshot of their current tracked OJT progress from Overview.

**Architecture:** Compose export data on the Laravel backend from the current student's internship and the existing `OjtProgressAssistant`. Add only scoped count queries, render one white A4 Blade view to PDF, and return it as an attachment. The Vue Overview requests a Blob and triggers a browser download with busy and error feedback.

**Tech Stack:** Laravel 12, PHP 8.3 on Render, Sanctum, Eloquent, Blade, `barryvdh/laravel-dompdf:^3.1`, Vue 3, Axios, Vitest, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-24-ojt-progress-summary-export-design.md`

## Global Constraints

- Use one main agent and execute tasks sequentially, per `AGENTS.md`.
- Do not commit or push without a separate explicit user request, per `AGENTS.md`.
- Do not edit real `.env` files, run destructive database commands, or add schema changes.
- The export belongs only to the authenticated student's current internship; accept no ownership ID from the client.
- Reuse `OjtProgressAssistant` for progress, pace, attention, and readiness. Tasks and optional requirements never block readiness.
- PDF output is a white, grayscale-readable A4 summary. Avoid remote assets, scripts, official-completion language, and persisted PDF files.
- Generate `OJT-Progress-Summary-YYYY-MM-DD.pdf` using the `Asia/Manila` date.
- Preserve the exact disclaimer in the spec. Only five attention items and five incomplete required-requirement titles may be printed.
- During implementation, run the narrowest relevant tests for each task, then the documented frontend and backend suites and `git diff --check` before completion.

## Review Focus

1. A forged `student_id` or `internship_id` query must not change whose PDF is generated; Task 2 pins the endpoint to the authenticated user.
2. Empty data and a future OJT start must still produce a readable PDF with `0m` and `Not started`; Tasks 1 and 2 cover both.
3. A due date equal to Philippine today is not overdue, even near UTC midnight; Task 1 uses a fixed `Asia/Manila` clock.
4. Long or HTML-like names and titles must wrap as escaped text, without becoming markup or disappearing; Task 2 tests the rendered Blade view.
5. A slow or failed Render request must restore the export button without a duplicate download; Task 3 covers busy, timeout, error, and retry states.

---

## File map

| File | Responsibility |
| --- | --- |
| `backend/app/Services/OjtProgressSummary.php` | Combine assistant output, student name, timestamp, and scoped aggregate counts. |
| `backend/tests/Feature/OjtProgressSummaryTest.php` | Verify counts, time boundary, ownership, empty state, and assistant reuse. |
| `backend/app/Http/Controllers/Student/SummaryExportController.php` | Resolve current internship and return the PDF attachment. |
| `backend/resources/views/exports/ojt-summary.blade.php` | Print-friendly A4 content and safe display wording. |
| `backend/tests/Feature/SummaryExportEndpointTest.php` | Verify auth, PDF response, current-student scope, and rendered text. |
| `backend/routes/api.php` | Register one authenticated `GET /api/student/overview/export` route. |
| `backend/config/cors.php` | Expose `Content-Disposition` to the local cross-origin frontend. |
| `backend/Dockerfile` | Ensure Dompdf's font directory exists and is writable. |
| `backend/composer.json`, `backend/composer.lock` | Lock the PDF renderer and its transitive dependencies. |
| `frontend/src/services/ojt.js`, `frontend/src/services/ojt.test.js` | Request the PDF as a Blob and preserve the Axios response headers. |
| `frontend/src/views/student/OverviewView.vue`, `frontend/src/views/student/OverviewView.test.js` | Show export action, busy/error state, and trigger one download. |
| `PROJECT_CONTEXT.md`, `DESIGN.md`, `README.md` | Record the approved narrow export exception and user workflow. |

### Task 1: Compose student-scoped export data

**Files:**
- Create: `backend/app/Services/OjtProgressSummary.php`
- Create: `backend/tests/Feature/OjtProgressSummaryTest.php`

**Interfaces:**
- Consumes: `OjtProgressAssistant::build(Internship $internship, ?CarbonImmutable $today = null): array` and `Internship` relations.
- Produces: `OjtProgressSummary::build(Internship $internship, ?CarbonImmutable $generatedAt = null): array` containing `student_name`, `generated_at`, `overview`, `task_counts`, and `requirement_counts`.

- [x] **Step 1: Write a failing service test for counts and isolation.** Use `RefreshDatabase`, create two users and internships, and freeze `CarbonImmutable::parse('2026-09-24 00:30:00', 'Asia/Manila')`. The following is the central assertion; also create a completed task, a required completed requirement, and a second student's records so each count is exercised:

```php
$generatedAt = CarbonImmutable::parse('2026-09-24 00:30:00', 'Asia/Manila');
$student = User::factory()->create(['name' => 'John Paragas']);
$other = User::factory()->create();
$own = Internship::factory()->create([
    'student_id' => $student->id,
    'start_date' => '2026-09-01', 'end_date' => '2026-12-15',
    'work_days' => [1, 2, 3, 4, 5], 'expected_daily_minutes' => 480,
]);
$otherInternship = Internship::factory()->create(['student_id' => $other->id]);
Task::factory()->create(['internship_id' => $own->id, 'status' => 'to_do', 'due_date' => '2026-09-23']);
Task::factory()->create(['internship_id' => $own->id, 'status' => 'to_do', 'due_date' => '2026-09-24']);
Task::factory()->create(['internship_id' => $own->id, 'status' => 'in_progress', 'due_date' => null]);
Task::factory()->completed()->create(['internship_id' => $own->id, 'due_date' => '2026-09-23']);
Task::factory()->create(['internship_id' => $otherInternship->id, 'due_date' => '2026-09-23']);
Requirement::factory()->create(['internship_id' => $own->id, 'is_required' => true, 'status' => 'incomplete', 'due_date' => '2026-09-23']);
Requirement::factory()->completed()->create(['internship_id' => $own->id, 'is_required' => true]);
Requirement::factory()->create(['internship_id' => $own->id, 'is_required' => false, 'due_date' => '2026-09-23']);
Requirement::factory()->create(['internship_id' => $otherInternship->id, 'is_required' => true, 'due_date' => '2026-09-23']);

$summary = app(OjtProgressSummary::class)->build($own, $generatedAt);

$this->assertSame($student->name, $summary['student_name']);
$this->assertSame(4, $summary['task_counts']['total']);
$this->assertSame(1, $summary['task_counts']['overdue']);
$this->assertSame(2, $summary['requirement_counts']['total']);
$this->assertSame(1, $summary['requirement_counts']['incomplete']);
$this->assertSame(1, $summary['requirement_counts']['overdue']);
$this->assertSame(
    app(OjtProgressAssistant::class)->build($own, $generatedAt->startOfDay())['completion'],
    $summary['overview']['completion'],
);
```

  Add imports for `User`, `Internship`, `Task`, `Requirement`, `OjtProgressSummary`, `OjtProgressAssistant`, and `CarbonImmutable`. The two task dates above prove only `2026-09-23` is overdue. Add an empty internship case with all counts zero and a future-start case that preserves `not_started` from the assistant. Do not add a second readiness calculation.

- [x] **Step 2: Run the failing test.** From `backend`:

```powershell
php artisan test --filter=OjtProgressSummaryTest
```

  Expected: failure because `OjtProgressSummary` does not exist.

- [x] **Step 3: Implement the composer.** Keep all queries rooted in `$internship->tasks()` and `$internship->requirements()`; aggregate in SQL so pagination and list size cannot change the counts. Use the same Philippine date for the assistant and overdue comparisons:

```php
final class OjtProgressSummary
{
    public function __construct(private readonly OjtProgressAssistant $assistant) {}

    public function build(Internship $internship, ?CarbonImmutable $generatedAt = null): array
    {
        $generatedAt ??= CarbonImmutable::now('Asia/Manila');
        $generatedAt = $generatedAt->setTimezone('Asia/Manila');
        $today = $generatedAt->startOfDay();

        $tasks = $internship->tasks()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'to_do' THEN 1 ELSE 0 END) AS to_do")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status <> 'completed' AND due_date < ? THEN 1 ELSE 0 END) AS overdue", [$today->toDateString()])
            ->first();
        $requirements = $internship->requirements()->where('is_required', true)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status <> 'completed' THEN 1 ELSE 0 END) AS incomplete")
            ->selectRaw("SUM(CASE WHEN status <> 'completed' AND due_date < ? THEN 1 ELSE 0 END) AS overdue", [$today->toDateString()])
            ->first();

        return [
            'student_name' => $internship->student->name,
            'generated_at' => $generatedAt,
            'overview' => $this->assistant->build($internship, $today),
            'task_counts' => [
                'total' => (int) $tasks->total,
                'to_do' => (int) $tasks->to_do,
                'in_progress' => (int) $tasks->in_progress,
                'completed' => (int) $tasks->completed,
                'overdue' => (int) $tasks->overdue,
            ],
            'requirement_counts' => [
                'total' => (int) $requirements->total,
                'completed' => (int) $requirements->completed,
                'incomplete' => (int) $requirements->incomplete,
                'overdue' => (int) $requirements->overdue,
            ],
        ];
    }
}
```

  Add the `App\Models\Internship` and `Carbon\CarbonImmutable` imports and the `App\Services` namespace. Aggregate `SUM` is nullable for an empty relation; `(int) null` gives zero. Keep the returned integer-minute values in `overview` unchanged.

- [x] **Step 4: Re-run the focused test and inspect its assertions.**

```powershell
php artisan test --filter=OjtProgressSummaryTest
```

  Expected: all service tests pass. Review the SQL against SQLite and MySQL behavior; no separate client-side count query is needed.

### Task 2: Render and securely download one PDF

**Files:**
- Create: `backend/app/Http/Controllers/Student/SummaryExportController.php`
- Create: `backend/resources/views/exports/ojt-summary.blade.php`
- Create: `backend/tests/Feature/SummaryExportEndpointTest.php`
- Modify: `backend/routes/api.php`, `backend/config/cors.php`, `backend/Dockerfile`, `backend/composer.json`, `backend/composer.lock`

**Interfaces:**
- Consumes: `OjtProgressSummary::build(Internship, ?CarbonImmutable): array` from Task 1.
- Produces: authenticated `GET /api/student/overview/export`; response body starts `%PDF`, content type `application/pdf`, and attachment filename `OJT-Progress-Summary-YYYY-MM-DD.pdf`.

- [x] **Step 1: Add failing endpoint and view tests.** Mirror `backend/tests/Feature/OverviewEndpointTest.php`. Test a guest's JSON request returns 401, a signed-in student receives their own PDF despite forged query IDs, and a student without an internship receives 404. Freeze the clock in the test and assert the filename, MIME type, and PDF signature:

```php
$student = User::factory()->create();
$other = User::factory()->create();
$own = Internship::factory()->create(['student_id' => $student->id]);
$otherInternship = Internship::factory()->create(['student_id' => $other->id]);
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila'));

$this->getJson('/api/student/overview/export')->assertUnauthorized();

$response = $this->stateful()->actingAs($student)->get(
    '/api/student/overview/export?student_id='.$other->id.'&internship_id='.$otherInternship->id
);
$response->assertOk()->assertHeader('Content-Type', 'application/pdf');
$this->assertStringContainsString('OJT-Progress-Summary-2026-09-24.pdf',
    $response->headers->get('Content-Disposition'));
$this->assertStringStartsWith('%PDF', $response->getContent());
CarbonImmutable::setTestNow();
```

  Separately render the Blade view with `OjtProgressSummary::build(...)` and assert it contains the student's name, expected minute labels, Ready/Not ready wording, exact disclaimer, and five-title limit. Include a requirement titled `<script>alert(1)</script>` and assert the HTML contains `&lt;script&gt;` but not a real `<script>` element. Check long titles are present in full. For no logs, assert `0m`; for a future start, assert `Not started`.

- [x] **Step 2: Run the failing endpoint tests.** From `backend`:

```powershell
php artisan test --filter=SummaryExportEndpointTest
```

  Expected: 404 for the missing route or failure to locate the PDF view.

- [x] **Step 3: Install the approved PDF dependency.** The [upstream package requirements](https://github.com/barryvdh/laravel-dompdf/blob/master/composer.json) include PHP `^8.1` and Laravel 12; the project uses PHP 8.3 and Laravel 12. The [package API](https://github.com/barryvdh/laravel-dompdf#using) supports `Pdf::loadView(...)->setPaper('a4')->download(...)`. From `backend`:

```powershell
composer require "barryvdh/laravel-dompdf:^3.1"
composer check-platform-reqs
```

  Expected: `composer.json` and `composer.lock` update, the package installs, and platform requirements pass. If local PHP lacks `ext-dom`, enable it in XAMPP PHP rather than ignoring Composer's platform check. Do not publish a broad Dompdf config or enable remote assets/PHP execution.

- [x] **Step 4: Add route, controller, CORS header, and Docker font cache.** Register the route beside `/overview` in the existing `auth:sanctum` group. The controller's core must resolve the internship from `$request->user()` and delegate data assembly:

```php
final class SummaryExportController extends Controller
{
    public function __invoke(Request $request, OjtProgressSummary $summary)
    {
        $internship = $request->user()->currentInternship()->firstOrFail();
        $data = $summary->build($internship);
        $filename = 'OJT-Progress-Summary-'.$data['generated_at']->toDateString().'.pdf';

        return Pdf::loadView('exports.ojt-summary', $data)
            ->setPaper('a4')
            ->download($filename);
    }
}
```

  Import `Barryvdh\DomPDF\Facade\Pdf`, `App\Services\OjtProgressSummary`, `Controller`, and `Request`. Add this exact route:

```php
Route::get('/overview/export', SummaryExportController::class);
```

  In `backend/config/cors.php`, change `exposed_headers` to `['Content-Disposition']` so local `http://localhost:5173` can read the filename. In `backend/Dockerfile`, add `storage/fonts` to the existing `mkdir -p` list; the following existing `chmod -R ug+rwx storage bootstrap/cache` covers it. Keep the normal `font_dir` and `font_cache` defaults under storage.

- [x] **Step 5: Add a complete, bounded Blade template.** Use HTML/CSS that Dompdf supports: document header, simple tables/blocks, one CSS progress bar, and page-break controls. No Tailwind runtime, external image URL, script, or student-supplied HTML. Use escaped `{{ }}` for names and titles. The template must map these exact values:

```blade
@php
    $duration = static function (?int $minutes): string {
        if ($minutes === null) return 'Not available';
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        return $hours ? ($rest ? "{$hours}h {$rest}m" : "{$hours}h") : "{$rest}m";
    };
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $statusLabels = [
        'not_started' => 'Not started', 'on_track' => 'On track',
        'at_risk' => 'At risk', 'complete' => 'Complete',
        'deadline_passed' => 'Deadline passed', 'pace_unavailable' => 'Pace unavailable',
    ];
    $reasonLabels = [
        'deadline_passed' => 'Deadline passed', 'at_risk' => 'At risk',
        'overdue' => 'Overdue', 'due_today' => 'Due today',
        'due_soon' => 'Due soon', 'no_due_date' => 'Required',
    ];
    $blockers = $overview['completion']['incomplete_required_requirements'];
@endphp
<h1>OJT Progress Summary</h1>
<p>{{ $student_name }} · Generated {{ $generated_at->format('M j, Y g:i A') }} PHT</p>
<p>OJT period: {{ $overview['internship']['start_date'] ?? 'Not available' }} to {{ $overview['internship']['end_date'] ?? 'Not available' }}</p>
<p>Work days: {{ implode(', ', array_map(fn ($day) => $days[$day - 1] ?? '?', $overview['internship']['work_days'])) ?: 'Schedule not configured' }}</p>
<p>Expected OJT day: {{ $duration($overview['internship']['expected_daily_minutes']) }}</p>
<h2>Work-hour progress</h2>
<p>Rendered: {{ $duration($overview['progress']['rendered_minutes']) }} · Required: {{ $duration($overview['progress']['required_minutes']) }} · Remaining: {{ $duration($overview['progress']['remaining_minutes']) }} · {{ $overview['progress']['percentage'] }}%</p>
<h2>Current pace</h2>
<p>{{ $statusLabels[$overview['pace']['status']] ?? 'Pace unavailable' }}
@if ($overview['pace']['status'] !== 'pace_unavailable')
    · {{ $overview['pace']['remaining_scheduled_days'] }} scheduled days remaining · Required per day: {{ $duration($overview['pace']['required_daily_minutes']) }} · Expected per day: {{ $duration($overview['pace']['expected_daily_minutes']) }}
@endif
</p>
<h2>Tasks</h2>
<p>Total {{ $task_counts['total'] }} · To Do {{ $task_counts['to_do'] }} · In Progress {{ $task_counts['in_progress'] }} · Completed {{ $task_counts['completed'] }} · Overdue {{ $task_counts['overdue'] }}</p>
<h2>Required requirements</h2>
<p>Total {{ $requirement_counts['total'] }} · Completed {{ $requirement_counts['completed'] }} · Incomplete {{ $requirement_counts['incomplete'] }} · Overdue {{ $requirement_counts['overdue'] }}</p>
<h2>Needs Attention</h2>
@forelse ($overview['attention']['items'] as $item)
    <p>{{ $reasonLabels[$item['reason']] ?? 'Needs attention' }}: {{ $item['title'] }}</p>
@empty
    <p>Nothing urgent right now.</p>
@endforelse
<h2>Completion Readiness: {{ $overview['completion']['ready'] ? 'Ready' : 'Not ready' }}</h2>
@if ($overview['completion']['ready'])
    <p>Your tracked OJT requirements are complete.</p>
@else
    <p>Remaining hours: {{ $duration($overview['completion']['remaining_minutes']) }}. Incomplete required requirements: {{ count($blockers) }}.</p>
    @foreach (array_slice($blockers, 0, 5) as $item)
        <p>{{ $item['title'] }}</p>
    @endforeach
    @if (count($blockers) > 5)<p>and {{ count($blockers) - 5 }} more</p>@endif
@endif
<footer>Generated from the student’s tracked OJT data for personal progress monitoring. This is not official verification of attendance or internship completion by a school or company.</footer>
```

  Add this document shell and progress bar around the mapped content. Use simple, Dompdf-supported CSS, 10–11pt dark text, neutral borders, and `page-break-inside: avoid` for short sections. Do not truncate long titles; let them wrap. Show `No tasks yet` or `No required requirements yet` where their total is zero.

```blade
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <style>
    @page { size: A4; margin: 16mm; }
    body { background: #fff; color: #111827; font: 10pt DejaVu Sans, sans-serif; }
    h1 { font-size: 18pt; margin: 0 0 7mm; }
    h2 { font-size: 11pt; border-bottom: 1px solid #9ca3af; margin-top: 6mm; }
    p { margin: 2mm 0; overflow-wrap: break-word; }
    section { page-break-inside: avoid; }
    .bar { height: 6mm; background: #e5e7eb; }
    .bar-fill { height: 6mm; background: #374151; }
    footer { border-top: 1px solid #9ca3af; margin-top: 7mm; padding-top: 3mm; font-size: 8pt; }
  </style>
</head>
<body>
  <div class="bar"><div class="bar-fill" style="width: {{ max(0, min(100, $overview['progress']['percentage'])) }}%"></div></div>
</body>
</html>
```

  Put the complete mapped content from the preceding block inside `<body>` and place the bar immediately after the work-hour values. Use plain punctuation if the chosen font does not render a glyph reliably.

- [x] **Step 6: Run the focused backend tests and build check.**

```powershell
php artisan test --filter=SummaryExportEndpointTest
php artisan test --filter=OjtProgressSummaryTest
```

  Expected: authentication, scoping, PDF signature, escaped content, empty data, and date-boundary tests pass. Inspect a generated PDF with the seeded demo data and with five long requirement titles: verify A4 pages, legible grayscale text, visible disclaimer, and a 1–2 page target without clipping. If Docker is available, run `docker build -f backend/Dockerfile backend` and confirm Composer's `--no-dev` install and `package:discover` succeed under PHP 8.3. If Docker is unavailable, report that deployment compatibility remains unverified locally.

### Task 3: Download the PDF from Overview

**Files:**
- Modify: `frontend/src/services/ojt.js`, `frontend/src/services/ojt.test.js`
- Modify: `frontend/src/views/student/OverviewView.vue`, `frontend/src/views/student/OverviewView.test.js`

**Interfaces:**
- Consumes: Task 2's `GET /api/student/overview/export` PDF attachment.
- Produces: `exportStudentSummary(): Promise<AxiosResponse<Blob>>` from `ojt.js` and an Overview export button with busy/error state.

- [x] **Step 1: Add failing service and view tests.** Extend the existing `vi.mock('../../services/ojt', ...)` with `exportStudentSummary: vi.fn()`. The service test pins binary response and a longer timeout for the Render free-instance wakeup:

```js
api.get.mockResolvedValue({ data: new Blob(['%PDF']), headers: { 'content-disposition': 'attachment; filename="OJT-Progress-Summary-2026-09-24.pdf"' } })
await exportStudentSummary()
expect(api.get).toHaveBeenCalledWith('/student/overview/export', { responseType: 'blob', timeout: 90000 })
```

  In `OverviewView.test.js`, assert export is absent during loading/error/no internship, present after a loaded response, disabled while a deferred export promise is pending, and triggers exactly one download on repeated clicks. Stub `URL.createObjectURL`, `URL.revokeObjectURL`, and the anchor click; assert the safe filename and one click. Reject once and assert the generic error is shown, button is enabled again, and a second attempt can succeed. Exercise a response with no readable `Content-Disposition` header and verify the Philippine-date fallback filename.

- [x] **Step 2: Run the targeted tests to see them fail.** From `frontend`:

```powershell
npm.cmd run test -- --run src/services/ojt.test.js src/views/student/OverviewView.test.js
```

  Expected: failure because the export service/button does not exist.

- [x] **Step 3: Add the binary service request and Overview handler.** The backend owns the document data; no client-side report calculations are added. The request line is:

```js
export const exportStudentSummary = () => api.get('/student/overview/export', {
  responseType: 'blob',
  timeout: 90000,
})
```

  Add `exporting = ref(false)` and `exportError = ref('')` in `OverviewView.vue`. Implement the click handler with one guarded request, MIME check, safe server filename, and Manila-date fallback. The response header is exposed in Task 2 for direct local development:

```js
const downloadSummary = async () => {
  if (exporting.value) return
  exporting.value = true
  exportError.value = ''
  try {
    const response = await exportStudentSummary()
    if (!response.headers['content-type']?.includes('application/pdf')) throw new Error('Unexpected export response')
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date())
    const value = (type) => parts.find((part) => part.type === type).value
    const fallback = `OJT-Progress-Summary-${value('year')}-${value('month')}-${value('day')}.pdf`
    const disposition = response.headers['content-disposition'] ?? ''
    const match = /filename="?(OJT-Progress-Summary-\d{4}-\d{2}-\d{2}\.pdf)"?/i.exec(disposition)
    const filename = match?.[1] ?? fallback
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch {
    exportError.value = "We couldn't generate your OJT summary. Please try again."
  } finally {
    exporting.value = false
  }
}
```

  Add a `PageHeader` action slot button only in the successful `overview` state; ensure it remains near the heading visually even though the state template follows the header. Show `Generating…` while busy, set `:disabled="exporting"`, and add a `role="alert"` paragraph for `exportError`. Do not add a confirmation modal or a Reports route.

- [x] **Step 4: Re-run the targeted tests and inspect mobile layout.**

```powershell
npm.cmd run test -- --run src/services/ojt.test.js src/views/student/OverviewView.test.js
npm.cmd run build
```

  Expected: tests and build pass. At a narrow viewport, verify the action remains visible and usable without horizontal scrolling. If a local signed-in browser session is available, download one PDF and inspect the filename and first page.

### Task 4: Align documentation and final integration

**Files:**
- Modify: `PROJECT_CONTEXT.md`, `DESIGN.md`, `README.md`
- Review: all files from Tasks 1–3

**Interfaces:**
- Consumes: implemented endpoint and Overview action.
- Produces: durable, accurate product and setup documentation.

- [x] **Step 1: Document the narrow export exception.** In `PROJECT_CONTEXT.md`, replace the blanket exclusion of “reports” with wording that allows only this personal PDF snapshot and still excludes reporting/analytics modules. In `DESIGN.md`, add one short Overview paragraph describing the print-friendly A4 PDF, header action, neutral style, and personal-tracking disclaimer. In `README.md`, add `GET /api/student/overview/export` to the authenticated API list and one line describing the Overview download action. Preserve the non-certification statement.

```markdown
The Overview can export a concise PDF snapshot of the signed-in student's
tracked progress for personal use. It is not an attendance record or official
school/company completion certificate.
```

- [x] **Step 2: Run the complete relevant checks.** These are the repo's documented scripts, plus whitespace validation:

```powershell
cd backend
php artisan test
cd ..\frontend
npm.cmd run test -- --run
npm.cmd run build
cd ..
git diff --check
```

  Expected: no regressions, build succeeds, and no whitespace errors. Report exact results; do not claim a Render deployment or downloaded PDF browser flow passed unless it was actually observed.

- [x] **Step 3: Review the final diff against the spec and hand off.** Confirm the PDF includes the disclaimer, all values come from the authenticated student's records, the exported count categories match their definitions, and there are no additional report routes or file storage. Leave unrelated `.agents/`, `.superpowers/`, and `skills-lock.json` untouched. Do not commit or push unless explicitly requested.
