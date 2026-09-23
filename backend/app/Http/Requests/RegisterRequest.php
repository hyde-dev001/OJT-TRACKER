<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20', 'in:Jr.,Sr.,II,III,IV,V'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
            'required_hours' => ['required', 'integer', 'min:1', 'max:10000'],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'An account already exists with this email.',
            'required_hours.required' => 'Required OJT hours must be greater than 0.',
            'required_hours.min' => 'Required OJT hours must be greater than 0.',
            'start_date.required' => 'Please select your OJT start date.',
            'end_date.required' => 'Please select your target end date.',
            'end_date.after' => 'Target end date must be after your OJT start date.',
            'work_days.required' => 'Select at least one OJT work day.',
            'work_days.min' => 'Select at least one OJT work day.',
            'work_days.*.between' => 'Choose work days from Monday through Sunday.',
            'expected_hours_per_day.required' => 'Please enter your expected hours per day.',
            'expected_hours_per_day.min' => 'Expected hours per day must be at least 0.5.',
            'expected_hours_per_day.max' => 'Expected hours per day cannot exceed 24.',
        ];
    }
}
