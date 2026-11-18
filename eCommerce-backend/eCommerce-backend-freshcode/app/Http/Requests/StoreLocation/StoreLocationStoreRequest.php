<?php

namespace App\Http\Requests\StoreLocation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create-location form. Address (line 1, city, state, pin code, country) is
 * required so the location is shippable and can register as a Shiprocket pickup;
 * the auto-seeded default location is created directly via the service, bypassing
 * this request, and may still start with a blank address until edited.
 *
 * `is_default` is NOT in this form — promotion runs through a dedicated
 * endpoint that atomically demotes the existing default in one transaction.
 */
class StoreLocationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'store_id'     => 'sometimes|required|integer|exists:stores,id',
            'name'         => 'required|string|max:120',

            'address_1'    => 'required|string|max:255',
            'address_2'    => 'nullable|string|max:255',
            'city'         => 'required|string|max:120',
            'state'        => 'required|string|max:120',
            'country_iso2' => 'required|string|size:2|exists:countries,iso2',
            'country_name' => 'nullable|string|max:120',
            'pin_code'     => 'required|string|max:20',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',

            'is_physical_storefront' => 'nullable|boolean',
            'fulfills_online_orders' => 'nullable|boolean',
            'offers_local_delivery'  => 'nullable|boolean',
            'offers_local_pickup'    => 'nullable|boolean',
            'offers_pos'             => 'nullable|boolean',

            'is_active'    => 'nullable|boolean',
        ];
    }
}
