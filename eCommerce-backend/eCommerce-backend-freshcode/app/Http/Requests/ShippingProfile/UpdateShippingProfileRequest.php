<?php

namespace App\Http\Requests\ShippingProfile;

use App\Models\ShippingProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a shipping profile.
 *
 * Same shape as StoreShippingProfileRequest minus store_id (the store can't
 * move — a profile is permanently bound to the store it was created under).
 * Default-profile name protection lives in the service: even if the field
 * comes through, the service drops it when is_default=true.
 *
 * Name uniqueness: scoped per-store, excluding the row we're editing and
 * soft-deleted rows. We resolve the store_id by loading the profile rather
 * than trusting any client-supplied value (the URL is the source of truth).
 */
class UpdateShippingProfileRequest extends FormRequest
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
        $profileId = (int) $this->route('profileId');
        $storeId   = (int) (ShippingProfile::whereKey($profileId)->value('store_id') ?? 0);

        return [
            'name'                 => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('shipping_profiles', 'name')
                    ->ignore($profileId)
                    ->where(fn ($q) => $q->where('store_id', $storeId)->whereNull('deleted_at')),
            ],

            'store_location_ids'   => 'nullable|array',
            'store_location_ids.*' => 'integer|exists:store_locations,id',

            'store_location_priority'   => 'nullable|array',
            'store_location_priority.*' => 'integer|exists:store_locations,id',

            'product_ids'          => 'nullable|array',
            'product_ids.*'        => 'integer|exists:products,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => __('labels.shipping_profile_name_unique'),
        ];
    }
}
