<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCompanyWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $commentRules = $this->route()->getActionMethod() === 'requestChanges'
            ? ['required', 'string', 'max:5000']
            : ['nullable', 'string', 'max:5000'];

        return ['comment' => $commentRules];
    }
}
