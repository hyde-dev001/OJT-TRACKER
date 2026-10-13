<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetCompanyInternPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|Password>> */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ];
    }
}
