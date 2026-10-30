<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrbitPredictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semi_major_axis_au' => 'required|numeric|min:0.001|max:1000',
            'star_mass_solar' => 'required|numeric|min:0.01|max:100',
            'user_guess_days' => 'nullable|numeric|min:0.01|max:10000000',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'semi_major_axis_au.required' => 'Semi-major axis (AU) is required.',
            'star_mass_solar.required' => 'Star mass (solar masses) is required.',
        ];
    }
}