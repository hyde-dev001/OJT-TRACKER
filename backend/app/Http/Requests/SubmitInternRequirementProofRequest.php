<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitInternRequirementProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
