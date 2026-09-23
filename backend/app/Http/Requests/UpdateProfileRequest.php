<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required_without:name', 'nullable', 'string', 'max:100'],
            'last_name' => ['required_without:name', 'nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20', 'in:Jr.,Sr.,II,III,IV,V'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'work_days' => ['sometimes', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'expected_hours_per_day' => ['sometimes', 'numeric', 'min:0.5', 'max:24'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => [
                'nullable',
                'string',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('work_days'))) {
            $workDays = array_values(array_unique(array_map(
                static fn (mixed $day): int => (int) $day,
                $this->input('work_days'),
            )));
            sort($workDays);

            $this->merge(['work_days' => $workDays]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Please enter your first name.',
            'last_name.required' => 'Please enter your last name.',
        ];
    }
}
