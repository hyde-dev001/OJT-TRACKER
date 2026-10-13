<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreCompanyInternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20', 'in:Jr.,Sr.,II,III,IV,V'],
            'age' => ['required', 'integer', 'between:1,120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
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
