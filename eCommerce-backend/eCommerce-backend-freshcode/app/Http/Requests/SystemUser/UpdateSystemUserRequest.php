<?php

namespace App\Http\Requests\SystemUser;

use App\Enums\GuardNameEnum;
use App\Enums\DefaultSystemRolesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemUserRequest extends FormRequest
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
        $seller = auth()->user()?->seller();

        $roleExistsRule = Rule::exists('roles', 'name');
        $accessPanel = auth()->user()['access_panel']->value;
        if ($seller && $accessPanel == 'seller') {
            $roleExistsRule = $roleExistsRule->where(function ($query) use ($seller) {
                $query->where('guard_name', 'seller')
                    ->where('team_id', $seller->id);
            });
        } else {
            $isSuperAdmin = auth()->user()?->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN(), GuardNameEnum::ADMIN->value);

            $roleExistsRule = $roleExistsRule->where(function ($query) use ($isSuperAdmin) {
                $query->where('guard_name', GuardNameEnum::ADMIN->value);

                if (! $isSuperAdmin) {
                    $query->whereNotIn('name', [
                        DefaultSystemRolesEnum::SUPER_ADMIN(),
                        DefaultSystemRolesEnum::SELLER(),
                        DefaultSystemRolesEnum::CUSTOMER(),
                    ]);
                }
            });
        }

        $storeExistsRule = Rule::exists('stores', 'id');
        if ($seller && $accessPanel == 'seller') {
            $storeExistsRule = $storeExistsRule->where('seller_id', $seller->id);
        }

        return [
            'name' => 'required|string|max:255',
            'mobile' => 'required|numeric|unique:users,mobile,' . $this->route('id'),
            'password' => 'nullable|string|min:8',
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', $roleExistsRule],
            'store_ids' => ['sometimes', 'array'],
            'store_ids.*' => ['integer', $storeExistsRule],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return [
            'name' => trans('labels.name'),
            'mobile' => trans('labels.mobile'),
            'password' => trans('labels.password'),
            'roles' => trans('labels.role'),
            'roles.*' => trans('labels.role'),
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'roles.array' => trans('validation.array', ['attribute' => trans('labels.role')]),
            'roles.*.exists' => trans('labels.role_not_found'),
        ];
    }
}
