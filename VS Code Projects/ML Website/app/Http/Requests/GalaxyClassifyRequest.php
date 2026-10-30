<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GalaxyClassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_id' => 'nullable|required_without:pixels|integer|exists:galaxy_images,id',
            'pixels' => 'nullable|required_without:image_id|array|size:256',
            'pixels.*' => 'numeric|between:0,1',
            'user_guess_label' => 'required|string',
            'dataset' => 'nullable|string',
            'session_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'user_guess_label.required' => 'You must enter your guess before running the neural network.',
            'pixels.array' => 'Pixels must be an array of 256 values.',
        ];
    }

}
