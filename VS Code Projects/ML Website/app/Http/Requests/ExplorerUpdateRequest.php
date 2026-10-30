<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExplorerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'callsign' => 'sometimes|required|string|min:2|max:20|regex:/^[a-zA-Z0-9_\-\s]+$/',
            'level' => 'sometimes|required|in:Beginner,Intermediate,Advanced',
        ];
    }

    public function messages(): array
    {
        return [
            'callsign.min' => 'Callsign must be at least 2 characters.',
            'callsign.max' => 'Callsign cannot exceed 20 characters.',
            'callsign.regex' => 'Callsign can only contain letters, numbers, spaces, hyphens, and underscores.',
        ];
    }
}