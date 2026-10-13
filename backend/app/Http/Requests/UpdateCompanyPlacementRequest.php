<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'expected_hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('work_days'))) {
            $this->merge([
                'work_days' => array_values(array_unique(array_map(
                    static fn (mixed $day): int => (int) $day,
                    $this->input('work_days'),
                ))),
            ]);
        }
    }
}
