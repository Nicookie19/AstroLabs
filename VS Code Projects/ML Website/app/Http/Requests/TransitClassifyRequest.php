<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitClassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transit_depth_ppm' => 'required|numeric|min:0.1|max:50000',
            'transit_duration_hours' => 'required|numeric|min:0.1|max:24',
            'signal_to_noise' => 'required|numeric|min:1|max:1000',
            'lambda' => 'required|numeric|min:0.001|max:1',
            'user_guess_label' => 'nullable|in:Exoplanet Transit Detected,Standard Star (no planet signal)',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'transit_depth_ppm.required' => 'Transit depth (ppm) is required.',
            'transit_duration_hours.required' => 'Transit duration (hours) is required.',
            'signal_to_noise.required' => 'Signal-to-noise ratio is required.',
            'lambda.required' => 'Lambda (margin penalty) is required.',
        ];
    }
}