<?php

namespace App\Http\Requests\StorePackage;

use App\Models\StorePackage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a package preset. Same shape as StoreStorePackageRequest minus
 * store_id (packages can't move stores — recreate elsewhere).
 */
class UpdateStorePackageRequest extends FormRequest
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
        $packageId = (int) $this->route('packageId');
        $storeId   = (int) (StorePackage::whereKey($packageId)->value('store_id') ?? 0);

        return [
            'name'            => [
                'sometimes', 'required', 'string', 'max:120',
                Rule::unique('store_packages', 'name')
                    ->ignore($packageId)
                    ->where(fn ($q) => $q->where('store_id', $storeId)->whereNull('deleted_at')),
            ],
            'type'            => ['sometimes', 'required', Rule::in([StorePackage::TYPE_BOX, StorePackage::TYPE_ENVELOPE, StorePackage::TYPE_SOFT_PACK])],
            'length_cm'       => ['required', 'integer', 'min:1', 'max:1000'],
            'breadth_cm'      => ['required', 'integer', 'min:1', 'max:1000'],
            'height_cm'       => ['required', 'integer', 'min:1', 'max:1000'],
            'empty_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999.999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => __('labels.store_package_name_unique'),
        ];
    }
}
