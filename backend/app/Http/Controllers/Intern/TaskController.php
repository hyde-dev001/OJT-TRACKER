<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitInternTaskProofRequest;
use App\Http\Resources\TaskResource;
use App\Models\Internship;
use App\Models\Task;
use App\Services\WorkItemProofs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $internship = $this->internship($request);

        return TaskResource::collection(
            $internship->tasks()
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function submit(
        SubmitInternTaskProofRequest $request,
        WorkItemProofs $proofs,
        int $task,
    ): TaskResource {
        $internship = $this->internship($request);
        $item = $proofs->replace(
            $internship->tasks(),
            $task,
            $request->file('proof'),
            $request->validated('comment'),
        );

        return new TaskResource($item);
    }

    public function proof(Request $request, WorkItemProofs $proofs, int $task): StreamedResponse
    {
        $item = $this->internship($request)->tasks()->whereKey($task)->firstOrFail();

        return $proofs->download($item, 'task-proof');
    }

    private function internship(Request $request): Internship
    {
        $internship = $request->user()->currentInternship()->firstOrFail();
        abort_if($internship->company_id === null, 404);

        return $internship;
    }
}
