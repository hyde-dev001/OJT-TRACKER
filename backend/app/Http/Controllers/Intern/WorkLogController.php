<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $internship = $request->user()->currentInternship()->firstOrFail();

        return WorkLogResource::collection(
            $internship->workLogs()->latest('work_date')->latest('id')->paginate(10)->withQueryString()
        );
    }
}
