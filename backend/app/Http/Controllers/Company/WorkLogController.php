<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyWorkLogTimeOutRequest;
use App\Http\Requests\UpdateCompanyWorkLogRequest;
use App\Http\Resources\WorkLogResource;
use App\Models\Internship;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WorkLogController extends Controller
{
    public function index(Request $request, User $intern): AnonymousResourceCollection
    {
        $internship = $this->internshipFor($request, $intern);

        return WorkLogResource::collection(
            $internship->workLogs()->latest('work_date')->latest('id')->paginate(10)->withQueryString()
        );
    }

    public function timeIn(Request $request, User $intern): WorkLogResource
    {
        $internship = $this->internshipFor($request, $intern);
        $now = now();
        $workDate = $now->toDateString();
        $this->ensureDateIsAllowed($internship, $workDate);

        try {
            $workLog = $internship->workLogs()->create([
                'work_date' => $workDate,
                'time_in' => $now->format('H:i'),
                'time_out' => null,
                'break_minutes' => 0,
                'rendered_minutes' => null,
                'status' => 'in_progress',
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'work_date' => 'A work session already exists for this intern and date.',
            ]);
        }

        return new WorkLogResource($workLog);
    }

    public function timeOut(StoreCompanyWorkLogTimeOutRequest $request, User $intern): WorkLogResource
    {
        $internship = $this->internshipFor($request, $intern);
        $workLog = $internship->workLogs()
            ->whereDate('work_date', now()->toDateString())
            ->whereNull('time_out')
            ->first();

        if (! $workLog) {
            throw ValidationException::withMessages([
                'time_out' => 'No open work session was found for this intern today. Correct the saved entry if time-out was missed.',
            ]);
        }

        $timeOut = now()->format('H:i');
        $breakMinutes = $request->integer('break_minutes');
        $renderedMinutes = $this->calculateMinutes($workLog->time_in, $timeOut, $breakMinutes);

        $workLog->update([
            'time_out' => $timeOut,
            'break_minutes' => $breakMinutes,
            'rendered_minutes' => $renderedMinutes,
            'status' => 'completed',
        ]);

        return new WorkLogResource($workLog->refresh());
    }

    public function update(
        UpdateCompanyWorkLogRequest $request,
        User $intern,
        WorkLog $workLog,
    ): WorkLogResource {
        $internship = $this->internshipFor($request, $intern);
        $workLog = $internship->workLogs()->whereKey($workLog->id)->firstOrFail();
        $data = $request->validated();

        if ($data['work_date'] !== $workLog->work_date->toDateString()) {
            $this->ensureDateIsAllowed($internship, $data['work_date']);
        }

        if ($internship->workLogs()
            ->whereDate('work_date', $data['work_date'])
            ->whereKeyNot($workLog->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'work_date' => 'A work session already exists for this intern and date.',
            ]);
        }

        $renderedMinutes = $data['time_out'] === null
            ? null
            : $this->calculateMinutes($data['time_in'], $data['time_out'], (int) $data['break_minutes']);

        try {
            $workLog->update([
                'work_date' => $data['work_date'],
                'time_in' => $data['time_in'],
                'time_out' => $data['time_out'],
                'break_minutes' => (int) $data['break_minutes'],
                'rendered_minutes' => $renderedMinutes,
                'status' => $data['time_out'] === null ? 'in_progress' : 'completed',
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'work_date' => 'A work session already exists for this intern and date.',
            ]);
        }

        return new WorkLogResource($workLog->refresh());
    }

    private function internshipFor(Request $request, User $intern): Internship
    {
        return $request->user()->company()->firstOrFail()->internships()
            ->where('student_id', $intern->id)
            ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
            ->firstOrFail();
    }

    private function ensureDateIsAllowed(Internship $internship, string $workDate): void
    {
        $startDate = $internship->start_date?->toDateString();
        if ($startDate !== null && $workDate < $startDate) {
            throw ValidationException::withMessages([
                'work_date' => "Work date cannot be before the intern's OJT start date.",
            ]);
        }

        $latestWorkDate = today()->toDateString();
        if ($internship->end_date && $internship->end_date->toDateString() < $latestWorkDate) {
            $latestWorkDate = $internship->end_date->toDateString();
        }
        if ($workDate > $latestWorkDate) {
            throw ValidationException::withMessages([
                'work_date' => "Work date cannot be after the intern's target end date or today.",
            ]);
        }

        $workDays = $internship->work_days;
        if (is_array($workDays) && $workDays !== []) {
            $isoWeekday = Carbon::createFromFormat('!Y-m-d', $workDate)->dayOfWeekIso;
            if (! in_array($isoWeekday, array_map('intval', $workDays), true)) {
                throw ValidationException::withMessages([
                    'work_date' => 'Choose a date that falls on one of the intern\'s configured OJT workdays.',
                ]);
            }
        }
    }

    private function calculateMinutes(string $timeIn, string $timeOut, int $breakMinutes): int
    {
        try {
            return WorkLog::calculateRenderedMinutes(substr($timeIn, 0, 5), $timeOut, $breakMinutes);
        } catch (InvalidArgumentException $exception) {
            $field = str_contains($exception->getMessage(), 'Break') ? 'break_minutes' : 'time_out';
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
