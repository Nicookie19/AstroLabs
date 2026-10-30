<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForumClassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'required|string|min:10|max:500',
            'user_guess_category' => 'nullable|in:Planetary Science,Stellar Physics,Space Exploration Tech',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question to classify.',
            'question.min' => 'Question must be at least 10 characters.',
        ];
    }
}