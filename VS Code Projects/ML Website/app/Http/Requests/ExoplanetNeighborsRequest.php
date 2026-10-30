<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExoplanetNeighborsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'radius_earths' => 'required|numeric|min:0.01|max:50',
            'bulk_density_g_cm3' => 'required|numeric|min:0.01|max:100',
            'equilibrium_temp_k' => 'required|numeric|min:10|max:5000',
            'k' => 'required|integer|in:1,3,5,7,9',
            'user_guess_type' => 'nullable|in:Terrestrial/Super-Earth,Neptune-like,Gas giant',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'radius_earths.required' => 'Planet radius (Earth radii) is required.',
            'bulk_density_g_cm3.required' => 'Bulk density (g/cm³) is required.',
            'equilibrium_temp_k.required' => 'Equilibrium temperature (K) is required.',
            'k.required' => 'K value (number of neighbors) is required.',
        ];
    }
}