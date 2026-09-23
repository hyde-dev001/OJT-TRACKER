<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLogRequest;
use App\Http\Requests\UpdateWorkLogRequest;
use App\Http\Resources\WorkLogResource;
use App\Models\Internship;
use App\Models\WorkLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkLogController extends Controller
{
    public function index(Request $request): mixed
    {
        $internship = $this->internship($request);
        $query = $internship->workLogs();

        return WorkLogResource::collection(
            $query->latest('work_date')->latest('id')->paginate(10)->withQueryString()
        );
    }

    public function store(StoreWorkLogRequest $request): WorkLogResource
    {
        $internship = $this->internship($request);
        $this->authorize('create', [WorkLog::class, $internship]);
        $data = $request->validated();
        $this->ensureDateIsAvailable($internship, $data['work_date']);

        $data['internship_id'] = $internship->id;
        $data['rendered_minutes'] = WorkLog::calculateRenderedMinutes(
            $data['time_in'],
            $data['time_out'],
            (int) $data['break_minutes'],
        );
        $data['status'] = 'completed';

        return new WorkLogResource(WorkLog::create($data));
    }

    public function show(WorkLog $workLog): WorkLogResource
    {
        $this->authorize('view', $workLog);

        return new WorkLogResource($workLog);
    }

    public function update(UpdateWorkLogRequest $request, WorkLog $workLog): WorkLogResource
    {
        $this->authorize('update', $workLog);
        $data = $request->validated();
        $this->ensureDateIsAvailable($workLog->internship, $data['work_date'], $workLog);
        $data['rendered_minutes'] = WorkLog::calculateRenderedMinutes(
            $data['time_in'],
            $data['time_out'],
            (int) $data['break_minutes'],
        );

        $workLog->update($data);

        return new WorkLogResource($workLog->refresh());
    }

    public function destroy(WorkLog $workLog): JsonResponse
    {
        $this->authorize('delete', $workLog);
        $workLog->delete();

        return response()->json(status: 204);
    }

    private function internship(Request $request): Internship
    {
        return $request->user()->currentInternship()->firstOrFail();
    }

    private function ensureDateIsAvailable(Internship $internship, string $date, ?WorkLog $except = null): void
    {
        $query = $internship->workLogs()->whereDate('work_date', $date);

        if ($except) {
            $query->whereKeyNot($except->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'work_date' => 'A work log already exists for this date.',
            ]);
        }
    }
}
