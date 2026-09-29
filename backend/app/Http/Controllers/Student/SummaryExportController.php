<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\OjtProgressSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SummaryExportController extends Controller
{
    public function __invoke(Request $request, OjtProgressSummary $summary): Response
    {
        $internship = $request->user()->currentInternship()->firstOrFail();
        $data = $summary->build($internship);
        $filename = 'OJT-Progress-Summary-'.$data['generated_at']->toDateString().'.pdf';

        return Pdf::loadView('exports.ojt-summary', $data)
            ->setPaper('a4')
            ->download($filename);
    }
}
