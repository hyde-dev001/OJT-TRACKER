<?php

namespace App\Http\Requests;

use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreWorkLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['required', 'date_format:H:i', 'after:time_in'],
            'break_minutes' => ['required', 'integer', 'min:0'],
            'accomplishment_summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $internship = $this->user()?->currentInternship()->first();
            $workDate = Carbon::createFromFormat(
                '!Y-m-d',
                $this->string('work_date')->toString()
            );

            if ($internship?->start_date && $workDate->lt($internship->start_date)) {
                $validator->errors()->add('work_date', 'Work date cannot be before your OJT start date.');

                return;
            }

            $latestWorkDate = today();
            if ($internship?->end_date && $internship->end_date->lt($latestWorkDate)) {
                $latestWorkDate = $internship->end_date;
            }

            if ($workDate->gt($latestWorkDate)) {
                $validator->errors()->add('work_date', 'Work date cannot be after your target end date or today.');

                return;
            }

            if ($this->isOffDutyDate($internship, $workDate)) {
                $validator->errors()->add('work_date', 'Choose a date that falls on one of your configured OJT workdays.');

                return;
            }

            try {
                WorkLog::calculateRenderedMinutes(
                    $this->string('time_in')->toString(),
                    $this->string('time_out')->toString(),
                    $this->integer('break_minutes'),
                );
            } catch (InvalidArgumentException $exception) {
                $field = str_contains($exception->getMessage(), 'Break') ? 'break_minutes' : 'time_out';
                $validator->errors()->add($field, $exception->getMessage());
            }
        });
    }

    private function isOffDutyDate(?\App\Models\Internship $internship, Carbon $workDate): bool
    {
        $workDays = $internship?->work_days;

        if (! is_array($workDays) || $workDays === []) {
            return false;
        }

        $existingWorkLog = $this->route('workLog');
        if ($existingWorkLog instanceof WorkLog
            && $existingWorkLog->work_date?->toDateString() === $workDate->toDateString()) {
            return false;
        }

        return ! in_array($workDate->dayOfWeekIso, array_map('intval', $workDays), true);
    }
}
