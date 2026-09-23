<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\OjtProgressAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __invoke(Request $request, OjtProgressAssistant $assistant): JsonResponse
    {
        $internship = $request->user()->currentInternship()->firstOrFail();

        return response()->json([
            'data' => $assistant->build($internship),
        ]);
    }
}
