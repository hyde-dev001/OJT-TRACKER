<?php

namespace App\Services;

use App\Models\Internship;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class OjtProgressAssistant
{
    /** @return array<string, mixed> */
    public function build(Internship $internship, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today(config('app.timezone'));
        $today = $today->startOfDay();
        $timezone = $today->getTimezone()->getName();
        $progress = $internship->progressSummary();
        $renderedMinutes = (int) $progress['completed_minutes'];
        $requiredMinutes = (int) $internship->required_minutes;
        $remainingMinutes = max($requiredMinutes - $renderedMinutes, 0);
        $paceAvailable = $this->hasValidPaceSchedule($internship);
        $remainingScheduledDays = $paceAvailable
            ? $this->remainingScheduledDays($internship, $today, $timezone)
            : 0;
        $requiredDailyMinutes = ! $paceAvailable
            ? null
            : ($remainingMinutes === 0
                ? 0
                : ($remainingScheduledDays > 0
                    ? (int) ceil($remainingMinutes / $remainingScheduledDays)
                    : null));
        $status = $paceAvailable
            ? $this->status(
                $internship,
                $today,
                $remainingMinutes,
                $remainingScheduledDays,
                $requiredDailyMinutes,
                $timezone,
            )
            : 'pace_unavailable';
        $requirements = $internship->requirements()
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'title', 'due_date', 'is_required', 'status']);
        $tasks = $internship->tasks()
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'title', 'due_date', 'status']);

        return [
            'internship' => [
                'start_date' => $internship->start_date?->toDateString(),
                'end_date' => $internship->end_date?->toDateString(),
                'work_days' => array_map('intval', $internship->work_days ?? []),
                'expected_daily_minutes' => $internship->expected_daily_minutes,
            ],
            'progress' => [
                'required_minutes' => $requiredMinutes,
                'rendered_minutes' => $renderedMinutes,
                'remaining_minutes' => $remainingMinutes,
                'percentage' => $requiredMinutes > 0
                    ? min(100, (int) round(($renderedMinutes / $requiredMinutes) * 100))
                    : 0,
            ],
            'pace' => [
                'status' => $status,
                'remaining_scheduled_days' => $remainingScheduledDays,
                'required_daily_minutes' => $requiredDailyMinutes,
                'expected_daily_minutes' => $internship->expected_daily_minutes,
            ],
            'attention' => $this->attention(
                $internship,
                $requirements,
                $tasks,
                $today,
                $status,
                $remainingMinutes,
                $timezone,
            ),
            'completion' => $this->completion($requirements, $remainingMinutes),
        ];
    }

    private function remainingScheduledDays(
        Internship $internship,
        CarbonImmutable $today,
        string $timezone,
    ): int {
        $startDate = $this->date($internship->start_date, $timezone);
        $endDate = $this->date($internship->end_date, $timezone);

        if (! $startDate || ! $endDate || $today->gt($endDate)) {
            return 0;
        }

        $from = $today->lt($startDate) ? $startDate : $today;
        $workDays = array_values(array_filter(
            array_map('intval', $internship->work_days ?? []),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        ));

        if ($workDays === []) {
            return 0;
        }

        $count = 0;
        for ($date = $from; $date->lte($endDate); $date = $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $workDays, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function hasValidPaceSchedule(Internship $internship): bool
    {
        $workDays = $internship->work_days;
        $expectedDailyMinutes = (int) $internship->expected_daily_minutes;

        if (
            ! is_array($workDays)
            || $workDays === []
            || $internship->start_date === null
            || $internship->end_date === null
            || $internship->end_date->lt($internship->start_date)
            || $expectedDailyMinutes < 30
            || $expectedDailyMinutes > 1_440
        ) {
            return false;
        }

        $normalizedDays = array_map('intval', $workDays);

        return count($normalizedDays) === count(array_unique($normalizedDays))
            && count(array_filter(
                $normalizedDays,
                static fn (int $day): bool => $day >= 1 && $day <= 7,
            )) === count($normalizedDays);
    }

    private function status(
        Internship $internship,
        CarbonImmutable $today,
        int $remainingMinutes,
        int $remainingScheduledDays,
        ?int $requiredDailyMinutes,
        string $timezone,
    ): string {
        $startDate = $this->date($internship->start_date, $timezone);
        $endDate = $this->date($internship->end_date, $timezone);

        if ($startDate && $today->lt($startDate)) {
            return 'not_started';
        }

        if ($remainingMinutes === 0) {
            return 'complete';
        }

        if ($endDate && $today->gt($endDate)) {
            return 'deadline_passed';
        }

        if (
            $remainingScheduledDays > 0
            && $requiredDailyMinutes !== null
            && $internship->expected_daily_minutes !== null
            && $requiredDailyMinutes <= (int) $internship->expected_daily_minutes
        ) {
            return 'on_track';
        }

        return 'at_risk';
    }

    /** @return array{items: array<int, array<string, mixed>>, additional_count: int} */
    private function attention(
        Internship $internship,
        Collection $requirements,
        Collection $tasks,
        CarbonImmutable $today,
        string $status,
        int $remainingMinutes,
        string $timezone,
    ): array {
        $items = [];
        $endDate = $this->date($internship->end_date, $timezone);

        if ($remainingMinutes > 0 && $endDate && $today->gt($endDate)) {
            $items[] = [
                'type' => 'work_hours',
                'id' => null,
                'title' => 'Your OJT target date has passed.',
                'due_date' => $endDate->toDateString(),
                'reason' => 'deadline_passed',
                'priority' => 1,
                'href' => '/student/work-hours',
            ];
        } elseif ($status === 'at_risk') {
            $items[] = [
                'type' => 'pace',
                'id' => null,
                'title' => 'Your required daily pace is above your expected pace.',
                'due_date' => null,
                'reason' => 'at_risk',
                'priority' => 4,
                'href' => '/student/work-hours',
            ];
        }

        foreach ($requirements as $requirement) {
            if ($requirement->status === 'completed') {
                continue;
            }

            $dueDate = $this->date($requirement->due_date, $timezone);
            $classification = $this->classifyDueDate($dueDate, $today);

            if ($classification === null) {
                if (! $requirement->is_required || $dueDate !== null) {
                    continue;
                }

                $classification = ['reason' => 'no_due_date', 'priority' => 9];
            } elseif ($requirement->is_required) {
                $classification['priority'] = match ($classification['reason']) {
                    'overdue' => 2,
                    'due_today' => 5,
                    'due_soon' => 7,
                };
            } else {
                $classification['priority'] = match ($classification['reason']) {
                    'overdue' => 10,
                    'due_today' => 11,
                    'due_soon' => 12,
                };
            }

            $items[] = $this->attentionItem(
                'requirement',
                $requirement->id,
                $requirement->title,
                $dueDate?->toDateString(),
                $classification['reason'],
                $classification['priority'],
                '/student/requirements',
            );
        }

        foreach ($tasks as $task) {
            if ($task->status === 'completed') {
                continue;
            }

            $dueDate = $this->date($task->due_date, $timezone);
            $classification = $this->classifyDueDate($dueDate, $today);

            if ($classification === null) {
                continue;
            }

            $priority = match ($classification['reason']) {
                'overdue' => 3,
                'due_today' => 6,
                'due_soon' => 8,
            };
            $items[] = $this->attentionItem(
                'task',
                $task->id,
                $task->title,
                $dueDate?->toDateString(),
                $classification['reason'],
                $priority,
                '/student/tasks',
            );
        }

        usort($items, static function (array $left, array $right): int {
            $priority = $left['priority'] <=> $right['priority'];
            if ($priority !== 0) {
                return $priority;
            }

            $leftDate = $left['due_date'] ?? '9999-12-31';
            $rightDate = $right['due_date'] ?? '9999-12-31';
            $date = $leftDate <=> $rightDate;

            return $date !== 0 ? $date : (($left['id'] ?? 0) <=> ($right['id'] ?? 0));
        });

        $visibleItems = array_slice($items, 0, 5);

        return [
            'items' => $visibleItems,
            'additional_count' => max(0, count($items) - count($visibleItems)),
        ];
    }

    /** @return array{ready: bool, remaining_minutes: int, incomplete_required_requirements: array<int, array<string, mixed>>, blockers: array<int, string>} */
    private function completion(Collection $requirements, int $remainingMinutes): array
    {
        $incompleteRequired = $requirements
            ->filter(static fn ($requirement): bool => $requirement->is_required && $requirement->status !== 'completed')
            ->sortBy([
                ['due_date', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(static fn ($requirement): array => [
                'id' => $requirement->id,
                'title' => $requirement->title,
                'due_date' => $requirement->due_date?->toDateString(),
            ])
            ->all();
        $blockers = [];

        if ($remainingMinutes > 0) {
            $blockers[] = 'hours_remaining';
        }

        if ($incompleteRequired !== []) {
            $blockers[] = 'required_requirements_incomplete';
        }

        return [
            'ready' => $blockers === [],
            'remaining_minutes' => $remainingMinutes,
            'incomplete_required_requirements' => $incompleteRequired,
            'blockers' => $blockers,
        ];
    }

    /** @return array{reason: string, priority?: int}|null */
    private function classifyDueDate(?CarbonImmutable $dueDate, CarbonImmutable $today): ?array
    {
        if (! $dueDate) {
            return null;
        }

        if ($dueDate->lt($today)) {
            return ['reason' => 'overdue'];
        }

        if ($dueDate->equalTo($today)) {
            return ['reason' => 'due_today'];
        }

        if ($dueDate->lte($today->addDays(3))) {
            return ['reason' => 'due_soon'];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function attentionItem(
        string $type,
        int $id,
        string $title,
        ?string $dueDate,
        string $reason,
        int $priority,
        string $href,
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'due_date' => $dueDate,
            'reason' => $reason,
            'priority' => $priority,
            'href' => $href,
        ];
    }

    private function date(mixed $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse(
            is_string($value) ? $value : $value->toDateString(),
            $timezone,
        )->startOfDay();
    }
}
