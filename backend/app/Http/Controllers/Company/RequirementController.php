<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewCompanyWorkItemRequest;
use App\Http\Requests\StoreCompanyRequirementRequest;
use App\Http\Resources\RequirementResource;
use App\Models\Internship;
use App\Models\User;
use App\Services\WorkItemProofs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementController extends Controller
{
    public function index(Request $request, User $intern): AnonymousResourceCollection
    {
        $internship = $this->internshipFor($request, $intern);

        return RequirementResource::collection(
            $internship->requirements()
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function store(StoreCompanyRequirementRequest $request, User $intern): RequirementResource
    {
        $internship = $this->internshipFor($request, $intern);
        $data = $request->safe()->only(['title', 'description', 'due_date', 'is_required']);

        $requirement = $internship->requirements()->create([
            ...$data,
            'is_required' => $data['is_required'] ?? true,
            'status' => 'incomplete',
            'submission_status' => 'not_submitted',
        ]);

        return new RequirementResource($requirement);
    }

    public function approve(
        ReviewCompanyWorkItemRequest $request,
        User $intern,
        int $requirement,
    ): RequirementResource {
        return $this->review($request, $intern, $requirement, 'approved');
    }

    public function requestChanges(
        ReviewCompanyWorkItemRequest $request,
        User $intern,
        int $requirement,
    ): RequirementResource {
        return $this->review($request, $intern, $requirement, 'changes_requested');
    }

    public function proof(
        Request $request,
        WorkItemProofs $proofs,
        User $intern,
        int $requirement,
    ): StreamedResponse {
        $item = $this->internshipFor($request, $intern)->requirements()->whereKey($requirement)->firstOrFail();

        return $proofs->download($item, 'requirement-proof');
    }

    private function review(
        ReviewCompanyWorkItemRequest $request,
        User $intern,
        int $requirementId,
        string $decision,
    ): RequirementResource {
        $internship = $this->internshipFor($request, $intern);
        $requirement = DB::transaction(function () use ($request, $internship, $requirementId, $decision) {
            $requirement = $internship->requirements()->whereKey($requirementId)->lockForUpdate()->firstOrFail();
            abort_unless($requirement->submission_status === 'pending_review', 409);

            $data = [
                'submission_status' => $decision,
                'review_comment' => $request->validated('comment'),
                'reviewed_at' => now(),
            ];
            if ($decision === 'approved') {
                $data['status'] = 'completed';
                $data['completed_at'] = now();
            }
            $requirement->update($data);

            return $requirement->refresh();
        });

        return new RequirementResource($requirement);
    }

    private function internshipFor(Request $request, User $intern): Internship
    {
        return $request->user()->company()->firstOrFail()->internships()
            ->where('student_id', $intern->id)
            ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
            ->firstOrFail();
    }
}
