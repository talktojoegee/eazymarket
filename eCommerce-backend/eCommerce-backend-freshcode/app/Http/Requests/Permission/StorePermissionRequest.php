<?php

namespace App\Http\Requests\Permission;

use App\Enums\AdminPermissionEnum;
use App\Enums\SellerPermissionEnum;
use App\Traits\PanelAware;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    use PanelAware;

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
            'role' => 'required|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => ['string', Rule::in($this->allowedPermissions())],
        ];
    }

    private function allowedPermissions(): array
    {
        return $this->getPanel() === 'seller'
            ? SellerPermissionEnum::values()
            : AdminPermissionEnum::values();
    }
}
