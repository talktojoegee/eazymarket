<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class SearchPosProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'             => ['required', 'integer', 'exists:stores,id'],
            'q'                    => ['nullable', 'string', 'max:255'],
            'per_page'             => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_out_of_stock' => ['nullable'],
            'category_id'          => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id'             => __('labels.validation_attribute_store'),
            'q'                    => __('labels.validation_attribute_search_term'),
            'per_page'             => __('labels.validation_attribute_items_per_page'),
            'include_out_of_stock' => __('labels.validation_attribute_include_out_of_stock'),
            'category_id'          => __('labels.validation_attribute_category'),
        ];
    }
}
