<?php

namespace App\Http\Requests\Category;

use App\Enums\CategoryStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCategoryRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return self::baseRules($this->route('id'));
    }

    public static function baseRules(int|string|null $ignoreId = null): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function (string $attribute, $value, \Closure $fail) use ($ignoreId): void {
                    if ($value !== null && $value !== '' && $ignoreId !== null && (int) $value === (int) $ignoreId) {
                        $fail(__('labels.parent_cannot_be_self'));
                    }
                },
            ],
            'title' => ['required', 'string', 'max:191', Rule::unique('categories', 'title')->ignore($ignoreId)->whereNull('deleted_at')],
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'status' => ['nullable', new Enum(CategoryStatusEnum::class)],
            'requires_approval' => 'boolean',
            'commission' => 'nullable|numeric|min:0|max:100',
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
            'status' => $this->status ?? CategoryStatusEnum::INACTIVE->value,
            'requires_approval' => $this->requires_approval ?? false,
        ]);
    }
}
