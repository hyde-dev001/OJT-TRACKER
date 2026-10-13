<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitInternRequirementProofRequest;
use App\Http\Resources\RequirementResource;
use App\Models\Internship;
use App\Services\WorkItemProofs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $internship = $this->internship($request);

        return RequirementResource::collection(
            $internship->requirements()
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function submit(
        SubmitInternRequirementProofRequest $request,
        WorkItemProofs $proofs,
        int $requirement,
    ): RequirementResource {
        $internship = $this->internship($request);
        $item = $proofs->replace(
            $internship->requirements(),
            $requirement,
            $request->file('proof'),
            $request->validated('comment'),
        );

        return new RequirementResource($item);
    }

    public function proof(Request $request, WorkItemProofs $proofs, int $requirement): StreamedResponse
    {
        $item = $this->internship($request)->requirements()->whereKey($requirement)->firstOrFail();

        return $proofs->download($item, 'requirement-proof');
    }

    private function internship(Request $request): Internship
    {
        $internship = $request->user()->currentInternship()->firstOrFail();
        abort_if($internship->company_id === null, 404);

        return $internship;
    }
}
