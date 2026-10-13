<?php

namespace App\Http\Requests;

use App\Models\WorkLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class UpdateCompanyWorkLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['present', 'nullable', 'date_format:H:i', 'after:time_in'],
            'break_minutes' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('time_out') === null) {
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
}
