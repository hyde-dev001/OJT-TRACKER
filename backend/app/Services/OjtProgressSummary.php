<?php

namespace App\Services;

use App\Models\Internship;
use Carbon\CarbonImmutable;

final class OjtProgressSummary
{
    public function __construct(private readonly OjtProgressAssistant $assistant) {}

    /** @return array<string, mixed> */
    public function build(Internship $internship, ?CarbonImmutable $generatedAt = null): array
    {
        $generatedAt = ($generatedAt ?? CarbonImmutable::now('Asia/Manila'))
            ->setTimezone('Asia/Manila');
        $today = $generatedAt->startOfDay();

        $tasks = $internship->tasks()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'to_do' THEN 1 ELSE 0 END) AS to_do")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw(
                "SUM(CASE WHEN status <> 'completed' AND due_date < ? THEN 1 ELSE 0 END) AS overdue",
                [$today->toDateString()],
            )
            ->first();

        $requirements = $internship->requirements()
            ->where('is_required', true)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status <> 'completed' THEN 1 ELSE 0 END) AS incomplete")
            ->selectRaw(
                "SUM(CASE WHEN status <> 'completed' AND due_date < ? THEN 1 ELSE 0 END) AS overdue",
                [$today->toDateString()],
            )
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
