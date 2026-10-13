<?php

namespace App\Console\Commands;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\Task;
use App\Services\CompanyInternNotifications;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SendCompanyWorkItemDeadlineNotifications extends Command
{
    protected $signature = 'company-work-items:send-deadline-notifications';

    protected $description = 'Send OJT work-hour and work-item deadline notifications';

    public function handle(CompanyInternNotifications $notifications): int
    {
        $today = Carbon::today('Asia/Manila');

        foreach ([Task::class, Requirement::class] as $model) {
            $items = $model::query()
                ->whereDate('due_date', '<=', $today->toDateString())
                ->whereHas('internship', fn ($query) => $query->where(function ($account): void {
                    $account->where(fn ($company) => $company
                        ->whereNotNull('company_id')
                        ->whereHas('student', fn ($student) => $student->where('role', 'intern')))
                        ->orWhere(fn ($personal) => $personal
                            ->whereNull('company_id')
                            ->whereHas('student', fn ($student) => $student->where('role', 'student')));
                }))
                ->with('internship.student')
                ->lazy();

            foreach ($items as $item) {
                if ($this->needsInternAction($item)) {
                    $this->notifyForDueDate($item, $today, $notifications);
                }
            }
        }

        $this->notifyForMissedPersonalWorkHours($today->copy()->subDay(), $notifications);

        return self::SUCCESS;
    }

    private function notifyForMissedPersonalWorkHours(Carbon $workDate, CompanyInternNotifications $notifications): void
    {
        $internships = Internship::query()
            ->whereNull('company_id')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $workDate->toDateString())
            ->whereDate('end_date', '>=', $workDate->toDateString())
            ->whereHas('student', fn ($student) => $student->where('role', 'student'))
            ->whereDoesntHave('workLogs', fn ($workLogs) => $workLogs->whereDate('work_date', $workDate->toDateString()))
            ->with('student')
            ->lazy();

        foreach ($internships as $internship) {
            if (in_array($workDate->isoWeekday(), $internship->work_days ?? [])) {
                $notifications->missedWorkHours($internship->student, $workDate->toDateString());
            }
        }
    }

    private function needsInternAction(Model $item): bool
    {
        return $item->status !== 'completed'
            && ! in_array($item->submission_status, ['pending_review', 'approved'], true);
    }

    private function notifyForDueDate(Model $item, Carbon $today, CompanyInternNotifications $notifications): void
    {
        $recipient = $item->internship?->student;
        if ($recipient === null) {
            return;
        }

        $dueDate = Carbon::parse($item->due_date->toDateString(), 'Asia/Manila');

        if ($dueDate->isSameDay($today)) {
            $notifications->deadline($item, $recipient, 'due_today');
        } elseif ($dueDate->isBefore($today)) {
            $notifications->deadline($item, $recipient, 'overdue');
        }
    }
}
