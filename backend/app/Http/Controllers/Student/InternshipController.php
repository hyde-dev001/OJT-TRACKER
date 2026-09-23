<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInternshipRequest;
use App\Http\Resources\InternshipResource;
use Illuminate\Http\Request;

class InternshipController extends Controller
{
    public function show(Request $request): InternshipResource
    {
        return new InternshipResource(
            $request->user()->currentInternship()->firstOrFail()
        );
    }

    public function update(UpdateInternshipRequest $request): InternshipResource
    {
        $internship = $request->user()->currentInternship()->firstOrFail();
        $this->authorize('update', $internship);
        $internship->update([
            'required_minutes' => $request->integer('required_hours') * 60,
        ]);

        return new InternshipResource($internship->refresh());
    }
}
