<?php

namespace App\Services;

use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CompanyInternNotifications
{
    public function assignmentCreated(Task|Requirement $item, User $intern): void
    {
        $type = $this->itemType($item);
        $dueDate = $item->due_date?->toDateString();

        $this->store($intern, "assignment|{$type}|{$item->id}", [
            'event' => 'assignment_created',
            'title' => "New {$type} assigned",
            'message' => $dueDate
                ? "Your company assigned \"{$item->title}\" (due {$dueDate})."
                : "Your company assigned \"{$item->title}\".",
            'due_date' => $dueDate,
            'item_type' => $type,
            'item_id' => $item->id,
            'destination' => $this->destination($type, $item->id, $intern->id, false, $intern->role),
        ]);
    }

    public function proofSubmitted(Task|Requirement $item, User $companyHolder): void
    {
        $type = $this->itemType($item);
        $internId = $item->internship()->value('student_id');
        $internName = $item->internship?->student?->name ?? 'An intern';
        $proofVersion = $item->proof_path === null
            ? $this->eventVersion($item, 'submitted_at')
            : hash('sha256', $item->proof_path);

        $this->store($companyHolder, "proof|{$type}|{$item->id}|".$this->eventVersion($item, 'submitted_at')."|{$proofVersion}", [
            'event' => 'proof_submitted',
            'title' => ucfirst($type).' proof submitted',
            'message' => "{$internName} submitted proof for \"{$item->title}\".",
            'item_type' => $type,
            'item_id' => $item->id,
            'intern_id' => $internId,
            'destination' => $this->destination($type, $item->id, $internId, true),
        ]);
    }

    public function reviewed(Task|Requirement $item, User $intern, string $decision): void
    {
        if (! in_array($decision, ['approved', 'changes_requested'], true)) {
            throw new InvalidArgumentException('Unsupported work item review decision.');
        }

        $type = $this->itemType($item);
        $approved = $decision === 'approved';

        $this->store($intern, "review|{$type}|{$item->id}|{$decision}|".$this->eventVersion($item, 'reviewed_at'), [
            'event' => $decision,
            'title' => ucfirst($type).($approved ? ' approved' : ' changes requested'),
            'message' => $approved
                ? "Your \"{$item->title}\" was approved."
                : "Changes were requested for your \"{$item->title}\".",
            'item_type' => $type,
            'item_id' => $item->id,
            'destination' => $this->destination($type, $item->id, null, false, $intern->role),
        ]);
    }

    public function missedWorkHours(User $student, string $workDate): void
    {
        if ($student->role !== 'student') {
            return;
        }

        $this->store($student, "missed-work-hours|{$workDate}", [
            'event' => 'missed_work_hours',
            'title' => 'Work hours not logged',
            'message' => "No work hours were logged for your scheduled workday on {$workDate}.",
            'destination' => '/student/work-hours',
        ]);
    }

    public function deadline(Task|Requirement $item, User $intern, string $reminderType): void
    {
        if (! in_array($reminderType, ['due_today', 'overdue'], true)) {
            throw new InvalidArgumentException('Unsupported work item deadline reminder.');
        }
        if ($item->due_date === null) {
            return;
        }

        $type = $this->itemType($item);
        $dueDate = $item->due_date->toDateString();
        $dueToday = $reminderType === 'due_today';

        $this->store($intern, "deadline|{$type}|{$item->id}|{$dueDate}|{$reminderType}", [
            'event' => $reminderType,
            'title' => $dueToday ? 'Due today' : 'Overdue',
            'message' => $dueToday
                ? "Your \"{$item->title}\" {$type} is due today."
                : "Your \"{$item->title}\" {$type} is overdue.",
            'item_type' => $type,
            'item_id' => $item->id,
            'due_date' => $dueDate,
            'destination' => $this->destination($type, $item->id, null, false, $intern->role),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function store(User $recipient, string $identity, array $data): void
    {
        $timestamp = now();
        $deduplicationKey = hash('sha256', implode('|', [
            $recipient->getMorphClass(),
            (string) $recipient->getKey(),
            $identity,
        ]));

        DB::table('notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'type' => $data['event'],
            'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => $recipient->getKey(),
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'deduplication_key' => $deduplicationKey,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function itemType(Model $item): string
    {
        return match (true) {
            $item instanceof Task => 'task',
            $item instanceof Requirement => 'requirement',
            default => throw new InvalidArgumentException('Unsupported work item type.'),
        };
    }

    private function eventVersion(Model $item, string $field): string
    {
        return $item->{$field}?->toISOString() ?? $item->updated_at?->toISOString() ?? (string) $item->getKey();
    }

    private function destination(
        string $type,
        int|string $itemId,
        int|string|null $internId = null,
        bool $company = false,
        ?string $role = null,
    ): string
    {
        $plural = $type === 'task' ? 'tasks' : 'requirements';
        $singular = $type;

        if ($company) {
            return "/company/{$plural}?intern_id={$internId}&{$singular}_id={$itemId}";
        }

        if ($role === 'student') {
            return "/student/{$plural}";
        }

        return "/intern/{$plural}?{$singular}_id={$itemId}";
    }
}
