<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GalaxyRevealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_id' => 'required|integer|exists:galaxy_images,id',
            'run_id' => 'nullable|integer',
            'user_guess_label' => 'nullable|string',
            'model_guess_label' => 'nullable|string',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'image_id.required' => 'Image ID is required.',
            'user_guess_label.required' => 'Your guess is required.',
            'model_guess_label.required' => 'Model guess is required.',
        ];
    }
}
