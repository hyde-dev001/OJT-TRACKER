<?php

namespace App\Http\Requests;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('due_date') || blank($this->input('due_date'))) {
                return;
            }

            $intern = $this->route('intern');
            $internship = $this->user()?->company()->first()?->internships()
                ->where('student_id', $intern instanceof User ? $intern->id : $intern)
                ->whereHas('student', fn ($query) => $query->where('role', 'intern'))
                ->first();
            if (! $internship) {
                return;
            }

            $dueDate = Carbon::createFromFormat('!Y-m-d', $this->string('due_date')->toString());
            if ($internship->start_date && $dueDate->lt($internship->start_date)) {
                $validator->errors()->add('due_date', 'Due date cannot be before the internship start date.');

                return;
            }

            if ($internship->end_date && $dueDate->gt($internship->end_date)) {
                $validator->errors()->add('due_date', 'Due date cannot be after the internship end date.');
            }
        });
    }
}
