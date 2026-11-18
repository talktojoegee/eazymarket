<?php

namespace App\Http\Requests\Store;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
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
     * Bank fields live on Seller. The store's default ship-from location address
     * is collected here (`location.*`) and applied to the auto-seeded default
     * StoreLocation, which then registers with Shiprocket. Tax + documents stay.
     *
     * @return array<string, ValidationRule|array|string>
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

            'location'              => ['required', 'array'],
            'location.name'         => ['nullable', 'string', 'max:120'],
            'location.address_1'    => ['required', 'string', 'max:255'],
            'location.address_2'    => ['nullable', 'string', 'max:255'],
            'location.city'         => ['required', 'string', 'max:120'],
            'location.state'        => ['required', 'string', 'max:120'],
            'location.country_iso2' => ['required', 'string', 'size:2', 'exists:countries,iso2'],
            'location.country_name' => ['nullable', 'string', 'max:120'],
            'location.pin_code'     => ['required', 'string', 'max:20'],
            'location.latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'location.longitude'    => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
