@php
    $duration = static function (?int $minutes): string {
        if ($minutes === null) {
            return 'Not available';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0
            ? ($rest > 0 ? "{$hours}h {$rest}m" : "{$hours}h")
            : "{$rest}m";
    };
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $statusLabels = [
        'not_started' => 'Not started',
        'on_track' => 'On track',
        'at_risk' => 'At risk',
        'complete' => 'Complete',
        'deadline_passed' => 'Deadline passed',
        'pace_unavailable' => 'Pace unavailable',
    ];
    $reasonLabels = [
        'deadline_passed' => 'Deadline passed',
        'at_risk' => 'At risk',
        'overdue' => 'Overdue',
        'due_today' => 'Due today',
        'due_soon' => 'Due soon',
        'no_due_date' => 'Required',
    ];
    $progress = $overview['progress'];
    $pace = $overview['pace'];
    $completion = $overview['completion'];
    $blockers = $completion['incomplete_required_requirements'];
    $attentionItems = array_slice($overview['attention']['items'], 0, 5);
    $percentage = max(0, min(100, (int) $progress['percentage']));
    $workDays = array_map(
        static fn (int $day): string => $days[$day - 1] ?? '?',
        $overview['internship']['work_days'] ?? [],
    );
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4; margin: 15mm 14mm; }
        body {
            margin: 0;
            background: #fff;
            color: #111827;
            font: 10.5pt/1.4 "DejaVu Sans", sans-serif;
        }
        h1 { margin: 0 0 2mm; font-size: 19pt; }
        h2 {
            margin: 0 0 2mm;
            padding-bottom: 1mm;
            border-bottom: 1px solid #9ca3af;
            font-size: 11pt;
        }
        p { margin: 1.5mm 0; }
        .header { padding-bottom: 4mm; border-bottom: 2px solid #374151; }
        .muted { color: #4b5563; }
        .section { margin-top: 5mm; page-break-inside: avoid; }
        .details, .counts { width: 100%; border-collapse: collapse; }
        .details td, .counts td {
            width: 50%;
            padding: 1.5mm 2mm 1.5mm 0;
            vertical-align: top;
        }
        .label { color: #4b5563; font-size: 9pt; }
        .bar { height: 5mm; margin-top: 2mm; background: #e5e7eb; }
        .bar-fill { height: 5mm; background: #374151; }
        .item { margin: 1.5mm 0; padding-left: 3mm; border-left: 2px solid #6b7280; }
        .text { word-wrap: break-word; }
        .empty { color: #4b5563; font-style: italic; }
        .readiness { padding: 3mm; border: 1px solid #9ca3af; }
        footer {
            margin-top: 8mm;
            padding-top: 3mm;
            border-top: 1px solid #9ca3af;
            color: #374151;
            font-size: 8.5pt;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>OJT Progress Summary</h1>
        <p><strong>{{ $student_name }}</strong></p>
        <p class="muted">Generated {{ $generated_at->format('M j, Y g:i A') }} PHT</p>
        <table class="details">
            <tr>
                <td>
                    <span class="label">OJT period</span><br>
                    {{ $overview['internship']['start_date'] ?? 'Not available' }}
                    to {{ $overview['internship']['end_date'] ?? 'Not available' }}
                </td>
                <td>
                    <span class="label">Work days</span><br>
                    {{ $workDays !== [] ? implode(', ', $workDays) : 'Schedule not configured' }}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Expected hours per OJT day</span><br>
                    {{ $duration($overview['internship']['expected_daily_minutes']) }}
                </td>
            </tr>
        </table>
    </header>

    <section class="section">
        <h2>Work-hour progress</h2>
        <p>
            Rendered: {{ $duration($progress['rendered_minutes']) }} |
            Required: {{ $duration($progress['required_minutes']) }} |
            Remaining: {{ $duration($progress['remaining_minutes']) }} |
            {{ $percentage }}%
        </p>
        <div class="bar" role="img" aria-label="{{ $percentage }} percent of required hours rendered">
            <div class="bar-fill" style="width: {{ $percentage }}%"></div>
        </div>
    </section>

    <section class="section">
        <h2>Current pace</h2>
        <p>{{ $statusLabels[$pace['status']] ?? 'Pace unavailable' }}</p>
        @if ($pace['status'] !== 'pace_unavailable')
            <p>
                {{ $pace['remaining_scheduled_days'] }} scheduled days remaining |
                Required per day: {{ $duration($pace['required_daily_minutes']) }} |
                Expected per day: {{ $duration($pace['expected_daily_minutes']) }}
            </p>
        @endif
    </section>

    <section class="section">
        <h2>Tasks</h2>
        <table class="counts">
            <tr>
                <td>Total: {{ $task_counts['total'] }}</td>
                <td>To Do: {{ $task_counts['to_do'] }}</td>
            </tr>
            <tr>
                <td>In Progress: {{ $task_counts['in_progress'] }}</td>
                <td>Completed: {{ $task_counts['completed'] }}</td>
            </tr>
            <tr><td colspan="2">Overdue: {{ $task_counts['overdue'] }}</td></tr>
        </table>
        @if ($task_counts['total'] === 0)
            <p class="empty">No tasks yet.</p>
        @endif
    </section>

    <section class="section">
        <h2>Required requirements</h2>
        <table class="counts">
            <tr>
                <td>Total: {{ $requirement_counts['total'] }}</td>
                <td>Completed: {{ $requirement_counts['completed'] }}</td>
            </tr>
            <tr>
                <td>Incomplete: {{ $requirement_counts['incomplete'] }}</td>
                <td>Overdue: {{ $requirement_counts['overdue'] }}</td>
            </tr>
        </table>
        @if ($requirement_counts['total'] === 0)
            <p class="empty">No required requirements yet.</p>
        @endif
    </section>

    <section class="section">
        <h2>Needs Attention</h2>
        @forelse ($attentionItems as $item)
            <p class="item text">
                {{ $reasonLabels[$item['reason']] ?? 'Needs attention' }}: {{ $item['title'] }}
            </p>
        @empty
            <p class="empty">Nothing urgent right now.</p>
        @endforelse
    </section>

    <section class="section readiness">
        <h2>Completion Readiness: {{ $completion['ready'] ? 'Ready' : 'Not ready' }}</h2>
        <p>
            Remaining hours: {{ $duration($completion['remaining_minutes']) }}.
            Incomplete required requirements: {{ count($blockers) }}.
        </p>
        @if ($completion['ready'])
            <p class="muted">Saved hours meet the configured requirement and all required items are marked complete.</p>
        @else
            @foreach (array_slice($blockers, 0, 5) as $item)
                <p class="item text">{{ $item['title'] }}</p>
            @endforeach
            @if (count($blockers) > 5)
                <p>and {{ count($blockers) - 5 }} more</p>
            @endif
        @endif
    </section>

    <footer>
        Generated from the student’s tracked OJT data for personal progress monitoring. This is not official verification of attendance or internship completion by a school or company.
    </footer>
</body>
</html>
