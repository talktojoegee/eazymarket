<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class GetProductsByLocationRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'categories' => 'string|nullable',
            'brands' => 'string|nullable',
            'attribute_values' => 'string|nullable',
            'sort' => 'string|nullable',
            'store' => 'string|nullable',
            'exclude_product' => 'string|nullable',
            'search' => 'string|nullable|min:2|max:255',
            'include_child_categories' => 'nullable|boolean',
            'recommended' => 'nullable|boolean',
            'include_shipping' => 'nullable|boolean',
            'country_iso2' => 'nullable|string|size:2',
            'country_iso2s' => 'nullable|array|max:100',
            'country_iso2s.*' => 'string|size:2|distinct',
        ];
    }
}
