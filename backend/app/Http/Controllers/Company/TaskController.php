<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewCompanyWorkItemRequest;
use App\Http\Requests\StoreCompanyTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Internship;
use App\Models\User;
use App\Services\CompanyInternNotifications;
use App\Services\WorkItemProofs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    public function index(Request $request, User $intern): AnonymousResourceCollection
    {
        $internship = $this->internshipFor($request, $intern);

        return TaskResource::collection(
            $internship->tasks()
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function show(Request $request, User $intern, int $task): TaskResource
    {
        return new TaskResource($this->internshipFor($request, $intern)->tasks()->whereKey($task)->firstOrFail());
    }

    public function store(StoreCompanyTaskRequest $request, CompanyInternNotifications $notifications, User $intern): TaskResource
    {
        $internship = $this->internshipFor($request, $intern);

        $task = DB::transaction(function () use ($internship, $intern, $request, $notifications) {
            $task = $internship->tasks()->create([
                ...$request->safe()->only(['title', 'description', 'due_date']),
                'status' => 'to_do',
                'submission_status' => 'not_submitted',
            ]);
            $notifications->assignmentCreated($task, $intern);
            if ($task->due_date?->toDateString() === today('Asia/Manila')->toDateString()) {
                $notifications->deadline($task, $intern, 'due_today');
            }

            return $task;
        });

        return new TaskResource($task);
    }

    public function approve(
        ReviewCompanyWorkItemRequest $request,
        CompanyInternNotifications $notifications,
        User $intern,
        int $task,
    ): TaskResource {
        return $this->review($request, $notifications, $intern, $task, 'approved');
    }

    public function requestChanges(
        ReviewCompanyWorkItemRequest $request,
        CompanyInternNotifications $notifications,
        User $intern,
        int $task,
    ): TaskResource {
        return $this->review($request, $notifications, $intern, $task, 'changes_requested');
    }

    public function proof(Request $request, WorkItemProofs $proofs, User $intern, int $task): StreamedResponse
    {
        $item = $this->internshipFor($request, $intern)->tasks()->whereKey($task)->firstOrFail();

        return $proofs->download($item, 'task-proof');
    }

    private function review(
        ReviewCompanyWorkItemRequest $request,
        CompanyInternNotifications $notifications,
        User $intern,
        int $taskId,
        string $decision,
    ): TaskResource {
        $internship = $this->internshipFor($request, $intern);
        $task = DB::transaction(function () use ($request, $internship, $taskId, $decision, $intern, $notifications) {
            $task = $internship->tasks()->whereKey($taskId)->lockForUpdate()->firstOrFail();
            abort_unless($task->submission_status === 'pending_review', 409);

            $data = [
                'submission_status' => $decision,
                'review_comment' => $request->validated('comment'),
                'reviewed_at' => now(),
            ];
            if ($decision === 'approved') {
                $data['status'] = 'completed';
                $data['completed_at'] = now();
            }
            $task->update($data);
            $task = $task->refresh();
            $notifications->reviewed($task, $intern, $decision);

            return $task;
        });

        return new TaskResource($task);
    }

    private function internshipFor(Request $request, User $intern): Internship
    {
        return $request->user()->company()->firstOrFail()->internships()
            ->where('student_id', $intern->id)
            ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
            ->firstOrFail();
    }
}
