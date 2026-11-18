<?php

namespace App\Http\Requests\Brand;

use App\Enums\HomePageScopeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return self::baseRules($this->route('id'));
    }

    public static function baseRules(int|string|null $ignoreId = null): array
    {
        return [
            'scope_type' => ['required', Rule::in([HomePageScopeEnum::GLOBAL(), HomePageScopeEnum::CATEGORY()])],
            'scope_id' => [
                'required_if:scope_type,' . HomePageScopeEnum::CATEGORY(),
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->whereNull('parent_id');
                }),
            ],
            'title' => ['required', 'string', 'max:191', Rule::unique('brands', 'title')->ignore($ignoreId)],
            'description' => 'required|string|max:191',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'status' => 'nullable|string|in:active,inactive',
            'meta_title' => 'nullable|string|max:255',
            'meta_keywords' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'metadata' => 'nullable|array',
            'metadata.seo_title' => 'nullable|string|max:255',
            'metadata.seo_keywords' => 'nullable|array',
            'metadata.seo_keywords.*' => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?? 'inactive',
        ]);
    }
}
