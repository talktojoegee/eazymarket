<?php

namespace App\Http\Requests\Store;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetStoreByLocation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'search' => 'nullable|string|max:255',
            'recommended' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.numeric' => __('messages.latitude_numeric'),
            'latitude.between' => __('messages.latitude_between'),
            'longitude.numeric' => __('messages.longitude_numeric'),
            'longitude.between' => __('messages.longitude_between'),
        ];
    }
}
