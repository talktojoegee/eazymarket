<?php

namespace App\Http\Requests\ShippingProfile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a custom shipping profile.
 *
 * `is_default` is intentionally not accepted — the General profile is auto-
 * seeded by StoreObserver and switching defaults is service-only (phased in
 * a follow-up). store_location_ids / product_ids are filtered against the
 * store's actual rows inside the service, so we keep the validation light
 * here and let the service enforce ownership.
 *
 * Name uniqueness: scoped per-store (not global). Soft-deleted profiles are
 * excluded from the check so a seller can reuse a name they previously
 * deleted. Collation is the DB default (utf8mb4_unicode_ci) which gives
 * case-insensitive matching out of the box.
 */
class StoreShippingProfileRequest extends FormRequest
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
        $storeId = (int) $this->input('store_id');

        return [
            'store_id'             => 'required|integer|exists:stores,id',
            'name'                 => [
                'required',
                'string',
                'max:120',
                Rule::unique('shipping_profiles', 'name')
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
