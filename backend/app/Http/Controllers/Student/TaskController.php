<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Internship;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): mixed
    {
        $internship = $this->internship($request);
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', Rule::in(['all', 'to_do', 'in_progress', 'completed'])],
        ]);
        $filter = $filters['filter'] ?? 'all';
        $query = $internship->tasks();

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        return TaskResource::collection(
            $query
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function store(StoreTaskRequest $request): TaskResource
    {
        $data = $request->validated();
        $requestedInternship = Internship::findOrFail($data['internship_id']);
        $this->authorize('create', [Task::class, $requestedInternship]);
        $internship = $this->internship($request);

        $data['internship_id'] = $internship->id;
        $data['status'] = 'to_do';

        return new TaskResource(Task::create($data));
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);
        abort_unless($task->status !== 'completed', 409, 'Completed tasks cannot be edited.');

        $data = $request->validated();
        $data['internship_id'] = $task->internship_id;
        $task->update($data);

        return new TaskResource($task->refresh());
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        abort_unless($task->status !== 'completed', 409, 'Completed tasks cannot be deleted.');
        $task->delete();

        return response()->json(status: 204);
    }

    public function start(Task $task): TaskResource
    {
        $this->authorize('start', $task);

        abort_unless($task->start(), 409, 'Only to-do tasks can be started.');

        return new TaskResource($task->refresh());
    }

    public function complete(Task $task): TaskResource
    {
        $this->authorize('complete', $task);

        abort_unless($task->complete(), 409, 'Only in-progress tasks can be completed.');

        return new TaskResource($task->refresh());
    }

    private function internship(Request $request): Internship
    {
        return $request->user()->currentInternship()->firstOrFail();
    }
}
