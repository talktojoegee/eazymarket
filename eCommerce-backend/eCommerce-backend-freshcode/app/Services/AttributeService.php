<?php

namespace App\Services;

use App\Enums\Attribute\AttributeTypesEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeService
{
    public function resolveVariantOption(int $sellerId, string $name, string $valueName): array
    {
        $attributes = GlobalProductAttribute::query()->where('seller_id', $sellerId)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($name)])->limit(2)->get();
        if ($attributes->count() > 1) {
            throw ValidationException::withMessages(['attribute' => __('labels.bulk_upload_product_option_name_ambiguous', ['name' => $name])]);
        }
        $attribute = $attributes->first();
        if (! $attribute) {
            $attribute = $this->saveAttributeWithValues([
                'title' => $name, 'label' => $name, 'swatche_type' => 'text',
                'values' => [['title' => $valueName]],
            ], $sellerId);

            return ['attribute_id' => $attribute->id, 'value_id' => $attribute->values->first()->id];
        }
        $values = $attribute->values()->whereRaw('LOWER(title) = ?', [mb_strtolower($valueName)])->limit(2)->get();
        if ($values->count() > 1) {
            throw ValidationException::withMessages(['value' => __('labels.bulk_upload_product_option_value_ambiguous', ['attribute' => $name, 'value' => $valueName])]);
        }
        $value = $values->first();
        if (! $value) {
            if ($valueName === '') {
                throw ValidationException::withMessages(['value_id' => __('labels.bulk_upload_attribute_value_name_invalid')]);
            }
            if ($attribute->swatche_type !== AttributeTypesEnum::TEXT->value) {
                throw ValidationException::withMessages(['value_id' => __('labels.bulk_upload_attribute_configure_swatch_first')]);
            }
            $this->saveAttributeWithValues([
                'title' => $attribute->title, 'label' => $attribute->label, 'swatche_type' => $attribute->swatche_type,
                'values' => [['title' => $valueName]],
            ], $sellerId, $attribute);
            $value = $attribute->values()->whereRaw('LOWER(title) = ?', [mb_strtolower($valueName)])->firstOrFail();
        }

        return ['attribute_id' => $attribute->id, 'value_id' => $value->id];
    }

    /**
     * Create a new attribute for the authenticated seller.
     */
    public function createAttribute(array $data, int $sellerId): GlobalProductAttribute
    {
        $data['seller_id'] = $sellerId;

        return GlobalProductAttribute::create($data);
    }

    public function saveAttributeWithValues(
        array $data,
        int $sellerId,
        ?GlobalProductAttribute $attribute = null
    ): GlobalProductAttribute {
        return DB::transaction(function () use ($data, $sellerId, $attribute) {
            if ($attribute) {
                $attribute = GlobalProductAttribute::query()
                    ->where('seller_id', $sellerId)
                    ->lockForUpdate()
                    ->findOrFail($attribute->id);
                $attribute->update([
                    'title' => $data['title'],
                    'label' => $data['title'],
                    'swatche_type' => $data['swatche_type'],
                ]);
            } else {
                $attribute = GlobalProductAttribute::create([
                    'seller_id' => $sellerId,
                    'title' => $data['title'],
                    'label' => $data['title'],
                    'swatche_type' => $data['swatche_type'],
                ]);
            }

            $removedIds = collect($data['removed_value_ids'] ?? [])->map(fn ($id) => (int) $id);
            if ($removedIds->isNotEmpty()) {
                $removableValues = $attribute->values()
                    ->whereIn('id', $removedIds)
                    ->lockForUpdate()
                    ->get();

                if ($removableValues->contains(fn ($value) => $value->productVariantAttributeValue()->exists())) {
                    throw ValidationException::withMessages([
                        'removed_value_ids' => __('labels.attribute_value_used_in_variants'),
                    ]);
                }

                $removableValues->each->delete();
            }

            foreach ($data['values'] as $valueData) {
                $value = ! empty($valueData['id'])
                    ? $attribute->values()->lockForUpdate()->findOrFail((int) $valueData['id'])
                    : new GlobalProductAttributeValue(['global_attribute_id' => $attribute->id]);

                $value->global_attribute_id = $attribute->id;
                $value->title = trim($valueData['title']);

                if ($attribute->swatche_type === AttributeTypesEnum::IMAGE->value) {
                    $value->swatche_value = null;
                    $value->save();
                    if (($valueData['swatch_value'] ?? null) instanceof UploadedFile) {
                        $value->clearMediaCollection(SpatieMediaCollectionName::SWATCHE_IMAGE());
                        $value->addMedia($valueData['swatch_value'])
                            ->toMediaCollection(SpatieMediaCollectionName::SWATCHE_IMAGE());
                    }
                } else {
                    if ($value->exists && $value->getMedia(SpatieMediaCollectionName::SWATCHE_IMAGE())->isNotEmpty()) {
                        $value->clearMediaCollection(SpatieMediaCollectionName::SWATCHE_IMAGE());
                    }
                    $value->swatche_value = $attribute->swatche_type === AttributeTypesEnum::TEXT->value
                        ? $value->title
                        : $valueData['swatch_value'];
                    $value->save();
                }
            }

            return $attribute->fresh(['values' => fn ($query) => $query
                ->withCount('productVariantAttributeValue')
                ->orderBy('id')]);
        });
    }

    /**
     * Create multiple attribute values for a given attribute id.
     * Handles swatche types (text/color/image) consistent with web logic.
     *
     * @param  array  $titles  Array of value titles
     * @param  array|null  $swatcheValues  For text/color, array of values; for image, array of UploadedFile or null
     * @return array<GlobalProductAttributeValue>
     */
    public function createAttributeValues(int $attributeId, array $titles, ?array $swatcheValues = null): array
    {
        $created = [];

        DB::beginTransaction();
        try {
            /** @var GlobalProductAttribute $attribute */
            $attribute = GlobalProductAttribute::findOrFail($attributeId);
            $swatcheType = $attribute->swatche_type;

            foreach ($titles as $index => $title) {
                $value = new GlobalProductAttributeValue;
                $value->global_attribute_id = $attributeId;
                $value->title = $title;

                if ($swatcheType === AttributeTypesEnum::IMAGE->value) {
                    $value->swatche_value = null;
                    $value->save();

                    $file = $swatcheValues[$index] ?? null;
                    if ($file instanceof UploadedFile) {
                        $value->addMedia($file)->toMediaCollection(SpatieMediaCollectionName::SWATCHE_IMAGE());
                    }
                } else {
                    $value->swatche_value = $swatcheValues[$index] ?? null;
                    $value->save();
                }

                $created[] = $value;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $created;
    }
}
