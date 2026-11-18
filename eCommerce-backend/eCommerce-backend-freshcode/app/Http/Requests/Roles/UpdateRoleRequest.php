<?php

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Traits\PanelAware;

class UpdateRoleRequest extends FormRequest
{
    use PanelAware;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('id');

        if ($this->getPanel() === 'admin') {
            return [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('roles', 'name')
                        ->ignore($roleId)
                        ->where(fn ($q) => $q->where('guard_name', 'admin')),
                ],
            ];
        }

        if ($this->getPanel() === 'seller') {
            $seller = $this->ensureSeller();
            return [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('roles', 'name')
                        ->ignore($roleId)
                        ->where(fn ($q) => $q
                            ->where('guard_name', 'seller')
                            ->where('team_id', optional($seller)->id)
                        ),
                ],
            ];
        }

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
        ];
    }
}
