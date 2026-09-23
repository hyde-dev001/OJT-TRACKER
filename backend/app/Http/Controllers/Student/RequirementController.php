<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequirementRequest;
use App\Http\Requests\UpdateRequirementRequest;
use App\Http\Resources\RequirementResource;
use App\Models\Internship;
use App\Models\Requirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequirementController extends Controller
{
    public function index(Request $request): mixed
    {
        $internship = $this->internship($request);
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', Rule::in(['all', 'incomplete', 'completed', 'overdue'])],
        ]);
        $filter = $filters['filter'] ?? 'all';
        $query = $internship->requirements();

        if ($filter === 'overdue') {
            $query
                ->where('status', '!=', 'completed')
                ->whereDate('due_date', '<', today());
        } elseif ($filter !== 'all') {
            $query->where('status', $filter);
        }

        return RequirementResource::collection(
            $query
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function store(StoreRequirementRequest $request): RequirementResource
    {
        $data = $request->validated();
        $requestedInternship = Internship::findOrFail($data['internship_id']);
        $this->authorize('create', [Requirement::class, $requestedInternship]);
        $internship = $this->internship($request);

        $data['internship_id'] = $internship->id;
        $data['status'] = 'incomplete';

        return new RequirementResource(Requirement::create($data));
    }

    public function show(Requirement $requirement): RequirementResource
    {
        $this->authorize('view', $requirement);

        return new RequirementResource($requirement);
    }

    public function update(UpdateRequirementRequest $request, Requirement $requirement): RequirementResource
    {
        $this->authorize('update', $requirement);
        abort_unless($requirement->status !== 'completed', 409, 'Completed requirements cannot be edited.');

        $data = $request->validated();
        $data['internship_id'] = $requirement->internship_id;
        $requirement->update($data);

        return new RequirementResource($requirement->refresh());
    }

    public function destroy(Requirement $requirement): JsonResponse
    {
        $this->authorize('delete', $requirement);
        abort_unless($requirement->status !== 'completed', 409, 'Completed requirements cannot be deleted.');
        $requirement->delete();

        return response()->json(status: 204);
    }

    public function complete(Requirement $requirement): RequirementResource
    {
        $this->authorize('complete', $requirement);

        abort_unless($requirement->complete(), 409, 'Only incomplete requirements can be completed.');

        return new RequirementResource($requirement->refresh());
    }

    public function incomplete(Requirement $requirement): RequirementResource
    {
        $this->authorize('incomplete', $requirement);

        abort_unless($requirement->incomplete(), 409, 'Only completed requirements can be reopened.');

        return new RequirementResource($requirement->refresh());
    }

    private function internship(Request $request): Internship
    {
        return $request->user()->currentInternship()->firstOrFail();
    }
}
