<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExplorerOnboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'callsign' => 'required|string|min:2|max:20|regex:/^[a-zA-Z0-9_\-\s]+$/',
            'level' => 'required|in:Beginner,Intermediate,Advanced',
        ];
    }

    public function messages(): array
    {
        return [
            'callsign.required' => 'Please enter a callsign.',
            'callsign.min' => 'Callsign must be at least 2 characters.',
            'callsign.max' => 'Callsign cannot exceed 20 characters.',
            'callsign.regex' => 'Callsign can only contain letters, numbers, spaces, hyphens, and underscores.',
            'level.required' => 'Please select an experience level.',
            'level.in' => 'Invalid experience level.',
        ];
    }
}