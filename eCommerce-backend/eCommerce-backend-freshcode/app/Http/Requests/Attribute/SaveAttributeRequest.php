<?php

namespace App\Http\Requests\Attribute;

use App\Enums\Attribute\AttributeTypesEnum;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['label' => $this->input('title')]);
    }

    public function rules(): array
    {
        $sellerId = $this->sellerId();
        $attributeId = $this->route('id');

        return [
            'seller_id' => [
                Rule::requiredIf($this->isAdminPanel() && ! $attributeId),
                'nullable',
                'integer',
                Rule::exists('sellers', 'id'),
            ],
            'title' => [
                'required',
                'string',
                'max:191',
                Rule::unique('global_product_attributes', 'title')
                    ->ignore($attributeId)
                    ->where(fn ($query) => $query
                        ->where('seller_id', $sellerId)
                        ->whereNull('deleted_at')),
            ],
            'label' => ['required', 'string', 'max:191'],
            'swatche_type' => ['required', new Enum(AttributeTypesEnum::class)],
            'values' => ['required', 'array', 'min:1'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.title' => ['required', 'string', 'max:191'],
            'values.*.swatch_value' => ['nullable'],
            'removed_value_ids' => ['nullable', 'array'],
            'removed_value_ids.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $attribute = $this->attribute();
            $type = (string) $this->input('swatche_type');
            $valueIds = collect($this->input('values', []))
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();
            $removedIds = collect($this->input('removed_value_ids', []))
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($valueIds->intersect($removedIds)->isNotEmpty()) {
                $validator->errors()->add('values', __('labels.invalid_attribute_value'));
            }

            if ($attribute && $attribute->values()->exists() && $attribute->swatche_type !== $type) {
                $validator->errors()->add('swatche_type', __('labels.attribute_display_style_locked'));
            }

            if ($attribute) {
                $ownedIds = $attribute->values()
                    ->whereIn('id', $valueIds->merge($removedIds)->unique())
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);
                $foreignIds = $valueIds->merge($removedIds)->unique()->diff($ownedIds);

                if ($foreignIds->isNotEmpty()) {
                    $validator->errors()->add('values', __('labels.invalid_attribute_value'));
                }

                $hasUsedRemoval = $attribute->values()
                    ->whereIn('id', $removedIds)
                    ->whereHas('productVariantAttributeValue')
                    ->exists();
                if ($hasUsedRemoval) {
                    $validator->errors()->add('removed_value_ids', __('labels.attribute_value_used_in_variants'));
                }
            } elseif ($valueIds->isNotEmpty() || $removedIds->isNotEmpty()) {
                $validator->errors()->add('values', __('labels.invalid_attribute_value'));
            }

            $normalizedTitles = [];
            foreach ($this->input('values', []) as $index => $value) {
                $title = trim((string) ($value['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $normalizedTitle = mb_strtolower($title);
                if (isset($normalizedTitles[$normalizedTitle])) {
                    $validator->errors()->add("values.$index.title", __('labels.duplicate_attribute_value', ['value' => $title]));
                }
                $normalizedTitles[$normalizedTitle] = true;

                if ($type === AttributeTypesEnum::COLOR->value) {
                    $color = (string) data_get($this->all(), "values.$index.swatch_value", '');
                    if (! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                        $validator->errors()->add("values.$index.swatch_value", __('labels.invalid_attribute_color'));
                    }
                }

                if ($type === AttributeTypesEnum::IMAGE->value) {
                    $file = $this->file("values.$index.swatch_value");
                    $existingValue = $attribute && ! empty($value['id'])
                        ? $attribute->values()->find((int) $value['id'])
                        : null;

                    if (! $file && ! $existingValue?->getFirstMediaUrl('swatche_image')) {
                        $validator->errors()->add("values.$index.swatch_value", __('labels.attribute_image_required'));
                    }

                    if ($file instanceof UploadedFile) {
                        $fileValidator = validator(
                            ['file' => $file],
                            ['file' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]
                        );
                        if ($fileValidator->fails()) {
                            $validator->errors()->add("values.$index.swatch_value", $fileValidator->errors()->first('file'));
                        }
                    }
                }
            }

            if ($attribute && $normalizedTitles !== []) {
                $existingTitles = GlobalProductAttributeValue::query()
                    ->where('global_attribute_id', $attribute->id)
                    ->whereNotIn('id', $valueIds->merge($removedIds)->unique())
                    ->pluck('title')
                    ->map(fn ($title) => mb_strtolower(trim((string) $title)));
                $existingDuplicate = $existingTitles->intersect(array_keys($normalizedTitles))->isNotEmpty();

                if ($existingDuplicate) {
                    $validator->errors()->add('values', __('labels.attribute_value_already_exists'));
                }
            }
        });
    }

    private function attribute(): ?GlobalProductAttribute
    {
        $id = $this->route('id');
        $sellerId = $this->sellerId();

        if (! $id || $sellerId === 0) {
            return null;
        }

        return GlobalProductAttribute::query()
            ->where('seller_id', $sellerId)
            ->find($id);
    }

    private function sellerId(): int
    {
        if ($this->isAdminPanel()) {
            $attributeId = $this->route('id');
            if ($attributeId) {
                return (int) (GlobalProductAttribute::query()->find($attributeId)?->seller_id ?? 0);
            }

            return (int) $this->input('seller_id', 0);
        }

        return (int) (auth()->user()?->seller()?->id ?? 0);
    }

    private function isAdminPanel(): bool
    {
        return $this->routeIs('admin.*') || $this->is('admin/*');
    }
}
