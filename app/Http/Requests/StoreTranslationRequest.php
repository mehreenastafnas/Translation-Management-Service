<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Protect routes via middleware (e.g., auth:sanctum) in routes file.
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:191'],
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'content' => ['required', 'string'],
            'context' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ];
    }
}
