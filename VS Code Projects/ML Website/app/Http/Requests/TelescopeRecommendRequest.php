<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelescopeRecommendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bortle_scale' => 'required|integer|min:1|max:9',
            'target_id' => 'required|integer|exists:telescope_targets,id',
            'user_guess' => 'nullable|array',
            'user_guess.filter' => 'nullable|string',
            'user_guess.exposure' => 'nullable|string',
            'user_guess.aperture' => 'nullable|string',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'bortle_scale.required' => 'Bortle scale (1-9) is required.',
            'target_id.required' => 'Target selection is required.',
        ];
    }
}