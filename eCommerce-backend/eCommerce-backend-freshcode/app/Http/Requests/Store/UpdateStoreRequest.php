<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
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
     * Phase B/C — bank fields moved to Seller, location fields moved off the
     * store form (store location owns address). Tax + business documents stay.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'contact_email'  => 'required|email|max:255',
            'contact_number' => 'required|numeric|digits_between:7,15',

            'store_logo'   => 'required|image|mimes:jpeg,png,jpg,webp|max:1024',
            'store_banner' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:1024',

            'tax_name'   => 'required|string|max:255',
            'tax_number' => 'required|string|max:50',

            'metadata'                 => 'nullable|array',
            'metadata.seo_title'       => 'nullable|string|max:255',
            'metadata.seo_keywords'    => 'nullable|array',
            'metadata.seo_keywords.*'  => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',

            'market_ids'   => ['required', 'array', 'min:1'],
            'market_ids.*' => ['integer', 'exists:markets,id'],
        ];
    }
}
