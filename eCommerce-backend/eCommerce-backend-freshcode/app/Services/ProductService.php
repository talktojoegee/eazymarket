<?php

namespace App\Services;

use App\Enums\Product\ProductAttachmentModeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Product\ProductVideoTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Events\Product\ProductAfterCreate;
use App\Events\Product\ProductAfterUpdate;
use App\Models\Category;
use App\Models\CustomProductField;
use App\Models\CustomProductSection;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Market;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StoreLocationInventory;
use App\Models\StoreProductVariant;
use App\Services\Search\ProductSearchQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Available products in the given market with optional search and pagination.
     *
     * Replaces getAvailableProductsByZoneIds. A null market means "no market
     * filter" — admin / cross-market search.
     */
    public function getAvailableProductsByMarket(?Market $market, ?string $search = null, int $perPage = 15): LengthAwarePaginatorContract
    {
        $storeIds = Product::getStoreIdsForMarket($market);

        if ($market && empty($storeIds)) {
            return $this->emptyPaginator($perPage);
        }

        $query = empty($storeIds)
            ? Product::query()
                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                ->where('status', ProductStatusEnum::ACTIVE())
                ->with(['variants.attributes.attribute', 'variants.attributes.attributeValue', 'variantAttributes.attribute', 'variantAttributes.attributeValue', 'category', 'brand'])
                ->distinct('products.id')
            : $this->baseAvailabilityQuery($storeIds);

        if (! empty($search)) {
            $this->applySearch($query, $search);
        }

        $query->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * @deprecated Phase-2 — call getAvailableProductsByMarket(). The shim
     * delegates with $market=null so admin search keeps working.
     */
    public function getAvailableProductsByZoneIds(array $zoneIds, ?string $search = null, int $perPage = 15): LengthAwarePaginatorContract
    {
        return $this->getAvailableProductsByMarket(null, $search, $perPage);
    }

    /**
     * Build base product query constrained by approval/active status and availability in given stores.
     */
    protected function baseAvailabilityQuery(array $storeIds): Builder
    {
        return Product::query()
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->where('status', ProductStatusEnum::ACTIVE())
            ->with([
                'variants' => function ($q) use ($storeIds) {
                    $q->whereHas('storeProductVariants', function ($sq) use ($storeIds) {
                        $sq->whereIn('store_id', $storeIds);
                    });
                },
                'variants.storeProductVariants' => function ($q) use ($storeIds) {
                    $q->whereIn('store_id', $storeIds);
                },
                'variants.storeProductVariants.store',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
                'variants.storeVariantAddons' => function ($q) use ($storeIds) {
                    $q->whereIn('store_id', $storeIds);
                },
                'variants.storeVariantAddons.addonGroup',
                'variants.storeVariantAddons.addonItem',
                'variantAttributes.attribute',
                'variantAttributes.attributeValue',
                'category',
                'brand',
            ])
            ->whereHas('variants.storeProductVariants', function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds);
            })
            ->distinct('products.id');
    }

    /**
     * Full-text search over products.search_text, ranked by relevance.
     */
    protected function applySearch(Builder $query, string $search): void
    {
        $searcher = app(ProductSearchQuery::class);
        $mode = ProductSearchQuery::MODE_ALL;

        if ($searcher->canRelax($search)) {
            $probe = clone $query;
            $searcher->applyFilter($probe, $search, $mode);

            if (! $probe->exists()) {
                $mode = ProductSearchQuery::MODE_ANY;
            }
        }

        $searcher->applyFilter($query, $search, $mode);
        $searcher->applyRelevanceOrder($query, $search);
    }

    /**
     * Create an empty paginator to satisfy the method contract when no stores/products are found.
     */
    protected function emptyPaginator(int $perPage): LengthAwarePaginatorContract
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Build payloads for a single product group parsed from CSV bulk upload.
     * This centralizes complex mapping logic out of the controller.
     *
     * @param  array  $normalizedHeaders  Lowercased/trimmed CSV headers
     * @param  array  $rows  All CSV rows for a single product (same handle)
     * @return array [basePayload, variantsJson, pricing]
     */
    public function buildBulkUploadPayload(int $sellerId, array $normalizedHeaders, array $rows): array
    {
        $firstRow = $rows[0];
        $type = strtolower(trim((string) ($firstRow['type'] ?? '')));
        $title = trim((string) ($firstRow['title'] ?? ''));

        $basePayload = [
            'seller_id' => $firstRow['seller_id'] ?? $sellerId,
            'category_id' => (int) ($firstRow['category_id'] ?? 0),
            'brand_id' => ($firstRow['brand_id'] ?? '') !== '' ? (int) $firstRow['brand_id'] : null,
            'title' => $title,
            'type' => $type,
            'image_fit' => $firstRow['image_fit'] ?? 'cover',
            'image_src' => isset($firstRow['image_src']) ? trim((string) $firstRow['image_src']) : null,
            'short_description' => $firstRow['short_description'] ?? '',
            'description' => $firstRow['description'] ?? '',
            'base_prep_time' => ($firstRow['base_prep_time'] ?? '') !== '' ? (int) $firstRow['base_prep_time'] : 0,
            'minimum_order_quantity' => ($firstRow['minimum_order_quantity'] ?? '') !== '' ? (int) $firstRow['minimum_order_quantity'] : 1,
            'quantity_step_size' => ($firstRow['quantity_step_size'] ?? '') !== '' ? (int) $firstRow['quantity_step_size'] : 1,
            'total_allowed_quantity' => ($firstRow['total_allowed_quantity'] ?? '') !== '' ? (int) $firstRow['total_allowed_quantity'] : null,
            'is_returnable' => (string) ($firstRow['is_returnable'] ?? 0),
            'returnable_days' => ($firstRow['returnable_days'] ?? '') !== '' ? (int) $firstRow['returnable_days'] : null,
            'is_cancelable' => (string) ($firstRow['is_cancelable'] ?? 0),
            'cancelable_till' => $firstRow['cancelable_till'] ?? null,
            'allow_cod' => (string) ($firstRow['allow_cod'] ?? 1),
            'is_attachment_required' => (string) ($firstRow['is_attachment_required'] ?? 0),
            'attachment_mode' => $firstRow['attachment_mode'] ?? ProductAttachmentModeEnum::REQUIRED(),
            'featured' => (string) ($firstRow['featured'] ?? 0),
            'requires_otp' => (string) ($firstRow['requires_otp'] ?? 0),
            'video_type' => $firstRow['video_type'] ?? null,
            'video_link' => $firstRow['video_link'] ?? null,
            'warranty_period' => $firstRow['warranty_period'] ?? null,
            'guarantee_period' => $firstRow['guarantee_period'] ?? null,
            'made_in' => $firstRow['made_in'] ?? null,
            'hsn_code' => preg_match('/^\d{4}(\d{2}(\d{2})?)?$/', (string) ($firstRow['hsn_code'] ?? '')) ? $firstRow['hsn_code'] : null,
            'tags' => isset($firstRow['tags']) && $firstRow['tags'] !== '' ? array_map('trim', explode(',', $firstRow['tags'])) : [],
        ];

        $customFields = [];
        $titlesRaw = $firstRow['custom_fields_title'] ?? '';
        $valuesRaw = $firstRow['custom_fields_value'] ?? '';
        if (($titlesRaw !== null && $titlesRaw !== '') || ($valuesRaw !== null && $valuesRaw !== '')) {
            $titles = array_map('trim', explode(',', (string) $titlesRaw));
            $values = array_map('trim', explode(',', (string) $valuesRaw));
            $max = max(count($titles), count($values));
            for ($i = 0; $i < $max; $i++) {
                $label = $titles[$i] ?? '';
                $value = $values[$i] ?? '';
                if ($label !== '' && $value !== '') {
                    $customFields[$label] = $value;
                }
            }
        } else {
            foreach ($normalizedHeaders as $h) {
                $label = '';
                if (str_starts_with($h, 'custom:')) {
                    $label = trim(substr($h, strlen('custom:')));
                    $label = $label !== '' ? ucwords($label) : '';
                } elseif (str_starts_with($h, 'custom_')) {
                    $label = trim(substr($h, strlen('custom_')));
                    $label = $label !== '' ? ucwords(str_replace('_', ' ', $label)) : '';
                }
                if ($label !== '') {
                    $val = $firstRow[$h] ?? null;
                    if ($val !== null && $val !== '') {
                        $customFields[$label] = $val;
                    }
                }
            }
        }
        if (! empty($customFields)) {
            $basePayload['custom_fields'] = $customFields;
        }

        $variantMap = [];
        foreach ($rows as $r) {
            $opt1Name = trim((string) ($r['option1_name'] ?? ''));
            $opt1Val = trim((string) ($r['option1_value'] ?? ''));
            $opt2Name = trim((string) ($r['option2_name'] ?? ''));
            $opt2Val = trim((string) ($r['option2_value'] ?? ''));
            $opt3Name = trim((string) ($r['option3_name'] ?? ''));
            $opt3Val = trim((string) ($r['option3_value'] ?? ''));

            $key = implode('|', [$opt1Val, $opt2Val, $opt3Val]);
            if (! isset($variantMap[$key])) {
                $parts = [];
                if ($opt1Name && $opt1Val) {
                    $parts[] = "$opt1Val";
                }
                if ($opt2Name && $opt2Val) {
                    $parts[] = "$opt2Val";
                }
                if ($opt3Name && $opt3Val) {
                    $parts[] = "$opt3Val";
                }
                $variantTitle = count($parts) ? implode(' / ', $parts) : 'Default Title';
                $variantMap[$key] = [
                    'row' => $r,
                    'title' => $variantTitle,
                    'stores' => [],
                ];
            }
            $variantMap[$key]['stores'][] = [
                'store_id' => ($r['store_id'] ?? '') !== '' ? (int) $r['store_id'] : null,
                'sku' => $r['variant_sku'] ?? null,
                'price' => ($r['variant_price'] ?? '') !== '' ? (float) $r['variant_price'] : null,
                'special_price' => ($r['variant_special_price'] ?? '') !== '' ? (float) $r['variant_special_price'] : null,
                'cost' => ($r['variant_cost'] ?? '') !== '' ? (float) $r['variant_cost'] : null,
                'stock' => ($r['variant_stock'] ?? '') !== '' ? (int) $r['variant_stock'] : 0,
            ];
        }

        $pricing = [];
        $variantsJson = [];
        $generatedId = 1000;
        $defaultMarked = false;

        foreach ($variantMap as $bundle) {
            $r = $bundle['row'];
            $variantId = $generatedId++;
            $variantIsDefault = strtolower(trim((string) ($r['variant_is_default'] ?? '')));
            $isDefault = $variantIsDefault === 'on' || $variantIsDefault === '1' || (! $defaultMarked && in_array($type, [ProductTypeEnum::SIMPLE->value, ProductTypeEnum::KIT->value], true));
            if ($isDefault) {
                $defaultMarked = true;
            }

            $resolvedAttributes = [];
            $optionPairs = [
                ['name' => trim((string) ($r['option1_name'] ?? '')), 'value' => trim((string) ($r['option1_value'] ?? ''))],
                ['name' => trim((string) ($r['option2_name'] ?? '')), 'value' => trim((string) ($r['option2_value'] ?? ''))],
                ['name' => trim((string) ($r['option3_name'] ?? '')), 'value' => trim((string) ($r['option3_value'] ?? ''))],
            ];
            foreach ($optionPairs as $pair) {
                if ($pair['name'] !== '' && $pair['value'] !== '') {
                    $av = $this->resolveOrCreateAttributeAndValue($sellerId, $pair['name'], $pair['value']);
                    if ($av) {
                        $resolvedAttributes[] = $av;
                    }
                }
            }

            $variantsJson[] = [
                'id' => $variantId,
                'title' => $bundle['title'],
                'attributes' => array_map(function ($av) {
                    return [
                        'attribute_id' => $av['attribute_id'],
                        'value_id' => $av['value_id'],
                    ];
                }, $resolvedAttributes),
                'barcode' => $r['variant_barcode'] ?? null,
                'weight' => ($r['variant_weight'] ?? '') !== '' ? (float) $r['variant_weight'] : null,
                'height' => ($r['variant_height'] ?? '') !== '' ? (float) $r['variant_height'] : null,
                'length' => ($r['variant_length'] ?? '') !== '' ? (float) $r['variant_length'] : null,
                'breadth' => ($r['variant_breadth'] ?? '') !== '' ? (float) $r['variant_breadth'] : null,
                'is_default' => $isDefault ? 'on' : 'off',
            ];
            foreach ($bundle['stores'] as $storeRow) {
                if (! $storeRow['store_id']) {
                    continue;
                }
                $storeBelongsToSeller = Store::where('id', $storeRow['store_id'])
                    ->where('seller_id', $sellerId)
                    ->exists();
                if (! $storeBelongsToSeller) {
                    throw new \Exception(__('labels.invalid_store_for_seller'));
                }
                if (! is_numeric($storeRow['price']) || ! is_numeric($storeRow['cost'])) {
                    continue;
                }
                $sp = $storeRow['special_price'];
                if ($sp !== null && $sp !== '' && $sp >= $storeRow['price']) {
                    $sp = $storeRow['price'];
                }
                if (in_array($type, [ProductTypeEnum::SIMPLE->value, ProductTypeEnum::KIT->value], true)) {
                    $pricing['store_pricing'][] = [
                        'store_id' => $storeRow['store_id'],
                        'price' => $storeRow['price'],
                        'special_price' => $sp,
                        'cost' => $storeRow['cost'],
                        'stock' => $storeRow['stock'] ?? 0,
                        'sku' => $storeRow['sku'] ?? null,
                    ];
                } else {
                    $pricing['variant_pricing'][] = [
                        'variant_id' => $variantId,
                        'store_id' => $storeRow['store_id'],
                        'price' => $storeRow['price'],
                        'special_price' => $sp,
                        'cost' => $storeRow['cost'],
                        'stock' => $storeRow['stock'] ?? 0,
                        'sku' => $storeRow['sku'] ?? null,
                    ];
                }
            }
        }

        if ($type === ProductTypeEnum::VARIANT() && ! collect($variantsJson)->firstWhere('is_default', 'on')) {
            if (isset($variantsJson[0])) {
                $variantsJson[0]['is_default'] = 'on';
            }
        }

        return [$basePayload, $variantsJson, $pricing];
    }

    /**
     * Find or create attribute and value for option pairs during bulk processing.
     */
    private function resolveOrCreateAttributeAndValue(int $sellerId, string $attributeName, string $valueTitle): ?array
    {
        $attrName = trim($attributeName);
        $valTitle = trim($valueTitle);
        if ($attrName === '' || $valTitle === '') {
            return null;
        }

        $attribute = GlobalProductAttribute::where('seller_id', $sellerId)
            ->whereRaw('LOWER(title) = ?', [strtolower($attrName)])
            ->first();
        if (! $attribute) {
            $attribute = GlobalProductAttribute::create([
                'seller_id' => $sellerId,
                'title' => $attrName,
                'label' => $attrName,
            ]);
        }

        $value = GlobalProductAttributeValue::where('global_attribute_id', $attribute->id)
            ->whereRaw('LOWER(title) = ?', [strtolower($valTitle)])
            ->first();
        if (! $value) {
            $value = GlobalProductAttributeValue::create([
                'global_attribute_id' => $attribute->id,
                'title' => $valTitle,
            ]);
        }

        return [
            'attribute_id' => $attribute->id,
            'value_id' => $value->id,
        ];
    }

    public static function getProductWithVariants(int $productId)
    {
        return Product::with([
            'variants.attributes',
            'variants.storeProductVariants',
            'variants.locationInventories',
            'taxClasses',
            'customProductSections.fields',
        ])->find($productId);
    }

    public function updateProduct(Product $product, array $validated, $request): array
    {
        return $this->processProduct($product, $validated, $request, 'update');
    }

    public function storeProduct(array $validated, $request): array
    {
        return $this->processProduct(null, $validated, $request, 'create');
    }

    /**
     * @throws \Exception
     */
    private function processProduct(?Product $product, array $validated, $request, string $mode): array
    {
        DB::beginTransaction();
        try {
            $handledByAdmin = $request->attributes->has('handled_by_admin')
                ? $request->attributes->getBoolean('handled_by_admin')
                : ($request->is('admin/*') || $request->routeIs('admin.*'));
            $criticalChanges = [];
            if ($mode === 'create') {
                $product = $this->createProduct($validated);
            } else {
                Product::whereKey($product->id)->lockForUpdate()->first();
                if (! $handledByAdmin) {
                    $criticalChanges = app(ProductApprovalService::class)->criticalChanges($product, $validated, $request);
                }
                $this->updateProductDetails($product, $validated);
            }
            $product->taxClasses()->sync($validated['tax_groups'] ?? []);
            $pricingData = json_decode($validated['pricing'], true);
            $incomingIsVariant = ($validated['type'] ?? $product->type) === 'variant' && isset($validated['variants_json']);
            $isVariant = $incomingIsVariant;
            if ($isVariant) {
                $this->processVariantProduct($product, $validated, $pricingData, $mode, $request);
            } else {
                if ($mode === 'update' && $product->type === 'variant') {
                    $this->cleanupAllVariants($product);
                }
                $this->processSimpleProduct($product, $validated, $pricingData, $mode);
            }

            if (($validated['type'] ?? $product->type) === ProductTypeEnum::KIT->value) {
                app(ComboPackService::class)->syncProductKit(
                    $product,
                    (int) $validated['kit_store_id'],
                    $validated['kit_components'],
                    $handledByAdmin,
                );
            } elseif ($mode === 'update' && $product->comboPack) {
                $product->comboPack->items()->delete();
                $product->comboPack->delete();
            }

            $mainImageReplacement = $this->shouldReplaceMainImage($product, $request);
            $this->handleMediaUploads($product, $request, $mainImageReplacement);

            if ($request->has('custom_sections')) {
                $this->syncCustomSectionsFromForm($product, $request);
            } elseif (! empty($validated['custom_sections_json']) && is_string($validated['custom_sections_json'])) {
                $this->syncCustomSections($product, $validated['custom_sections_json']);
            }

            if ((! $mainImageReplacement)
                && ! empty($validated['image_src'])
                && is_string($validated['image_src'])
            ) {
                try {
                    $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
                    SpatieMediaService::uploadFromUrl(
                        model: $product,
                        url: $validated['image_src'],
                        collectionName: SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE()
                    );
                } catch (\InvalidArgumentException $e) {
                    DB::rollBack();
                    throw $e;
                } catch (\Throwable $e) {
                    if ($request->attributes->getBoolean('bulk_main_image_required')) {
                        throw new \InvalidArgumentException(__('labels.bulk_upload_product_main_image_failed', ['reason' => $e->getMessage()]));
                    }
                    Log::warning('Product image fetch from URL failed', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($mode === 'update' && ! $handledByAdmin) {
                app(ProductApprovalService::class)->applySellerUpdate($product, $validated, $criticalChanges);
            }
            DB::commit();

            $pendingReview = (string) $product->verification_status === (string) ProductVarificationStatusEnum::PENDING();

            if (! $handledByAdmin && $pendingReview) {
                event($mode === 'create'
                    ? new ProductAfterCreate($product)
                    : new ProductAfterUpdate($product));
            }

            return [
                'success' => true,
                'product' => $product,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Sync custom product sections and their fields from a JSON payload.
     * Expected JSON structure:
     * [
     *   { id(optional), title, description, sort_order, fields: [ { id, sort_order } ] }
     * ]
     */
    private function syncCustomSections(Product $product, string $sectionsJson): void
    {
        $sections = json_decode($sectionsJson, true);
        if (! is_array($sections)) {
            return;
        }

        $keepSectionIds = [];

        foreach ($sections as $sectionData) {
            if (! is_array($sectionData)) {
                continue;
            }
            $sectionId = $sectionData['id'] ?? null;
            $payload = [
                'title' => $sectionData['title'] ?? '',
                'description' => $sectionData['description'] ?? null,
                'sort_order' => (int) ($sectionData['sort_order'] ?? 0),
            ];

            if ($sectionId) {
                $section = CustomProductSection::where('product_id', $product->id)->where('id', $sectionId)->first();
                if ($section) {
                    $section->update($payload);
                } else {
                    $section = CustomProductSection::create(array_merge($payload, [
                        'product_id' => $product->id,
                    ]));
                }
            } else {
                $section = CustomProductSection::create(array_merge($payload, [
                    'product_id' => $product->id,
                ]));
            }

            $keepSectionIds[] = $section->id;

            $fieldsArr = $sectionData['fields'] ?? [];
            $syncPayload = [];
            if (is_array($fieldsArr)) {
                foreach ($fieldsArr as $f) {
                    $fid = $f['id'] ?? null;
                    if (! $fid) {
                        continue;
                    }
                    $fieldExists = CustomProductField::where('id', $fid)->exists();
                    if (! $fieldExists) {
                        continue;
                    }
                    $syncPayload[$fid] = ['sort_order' => (int) ($f['sort_order'] ?? 0)];
                }
            }
            $section->fields()->sync($syncPayload);
        }

        if (! empty($keepSectionIds)) {
            CustomProductSection::where('product_id', $product->id)
                ->whereNotIn('id', $keepSectionIds)
                ->delete();
        } else {
            CustomProductSection::where('product_id', $product->id)->delete();
        }
    }

    /**
     * Sync custom product sections and fields from array-based form input with file uploads.
     * Expected structure in request:
     * custom_sections: [
     *   { id?, title, description?, sort_order?, fields: [ { id?, title?, description?, sort_order?, image? } ] }
     * ]
     */
    private function syncCustomSectionsFromForm(Product $product, $request): void
    {
        $sections = $request->input('custom_sections', []);
        $sectionsFiles = $request->file('custom_sections', []);

        if (! is_array($sections)) {
            return;
        }

        $keepSectionIds = [];

        foreach ($sections as $i => $sectionData) {
            if (! is_array($sectionData)) {
                continue;
            }

            $sectionId = $sectionData['id'] ?? null;
            $payload = [
                'title' => $sectionData['title'] ?? '',
                'description' => $sectionData['description'] ?? null,
                'sort_order' => (int) ($sectionData['sort_order'] ?? 0),
            ];

            if ($sectionId) {
                $section = CustomProductSection::where('product_id', $product->id)
                    ->where('id', $sectionId)
                    ->first();
                if ($section) {
                    $section->update($payload);
                } else {
                    $section = CustomProductSection::create(array_merge($payload, [
                        'product_id' => $product->id,
                    ]));
                }
            } else {
                $section = CustomProductSection::create(array_merge($payload, [
                    'product_id' => $product->id,
                ]));
            }

            $keepSectionIds[] = $section->id;

            $fieldsArr = $sectionData['fields'] ?? [];
            $fieldsFilesArr = $sectionsFiles[$i]['fields'] ?? [];
            $syncPayload = [];

            if (is_array($fieldsArr)) {
                foreach ($fieldsArr as $j => $fieldData) {
                    if (! is_array($fieldData)) {
                        continue;
                    }
                    $existingId = $fieldData['id'] ?? null;
                    $title = $fieldData['title'] ?? null;
                    $description = $fieldData['description'] ?? null;
                    $sortOrder = (int) ($fieldData['sort_order'] ?? 0);
                    $file = $fieldsFilesArr[$j]['image'] ?? null;

                    if ($existingId) {
                        $field = CustomProductField::whereKey($existingId)
                            ->whereHas('sections', fn ($q) => $q->where('product_id', $product->id))
                            ->first();
                        if ($field) {
                            $update = [];
                            if (! is_null($title)) {
                                $update['title'] = $title;
                            }
                            if (! is_null($description)) {
                                $update['description'] = $description;
                            }
                            if (! empty($update)) {
                                $field->update($update);
                            }
                            if ($file) {
                                try {
                                    $field->clearMediaCollection('image');
                                    $field->addMedia($file)->toMediaCollection('image');
                                } catch (\Throwable $e) {
                                }
                            }
                            $syncPayload[$field->id] = ['sort_order' => $sortOrder];
                        }
                    } else {
                        $field = CustomProductField::create([
                            'title' => $title ?? '',
                            'description' => $description,
                        ]);
                        if ($file) {
                            try {
                                $field->addMedia($file)->toMediaCollection('image');
                            } catch (\Throwable $e) {
                            }
                        }
                        $syncPayload[$field->id] = ['sort_order' => $sortOrder];
                    }
                }
            }

            $section->fields()->sync($syncPayload);
        }

        if (! empty($keepSectionIds)) {
            CustomProductSection::where('product_id', $product->id)
                ->whereNotIn('id', $keepSectionIds)
                ->delete();
        } else {
            CustomProductSection::where('product_id', $product->id)->delete();
        }
    }

    private function createProduct(array $validated)
    {
        $product = Product::create([
            'seller_id' => $validated['seller_id'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'title' => $validated['title'],
            'slug' => $validated['handle'] ?? generateUniqueSlug(model: Product::class, title: $validated['title']),
            'type' => $validated['type'],
            'status' => $validated['status'] ?? ProductStatusEnum::ACTIVE(),
            'base_prep_time' => $validated['base_prep_time'] ?? 0,
            'short_description' => $validated['short_description'],
            'description' => $validated['description'],
            'indicator' => $validated['indicator'] ?? null,
            'image_fit' => $validated['image_fit'] ?? 'cover',
            'minimum_order_quantity' => $validated['minimum_order_quantity'] ?? 1,
            'quantity_step_size' => $validated['quantity_step_size'] ?? 1,
            'total_allowed_quantity' => $validated['total_allowed_quantity'] ?? null,
            'is_returnable' => (string) ($validated['is_returnable'] ?? 0),
            'returnable_days' => $validated['returnable_days'] ?? null,
            'is_cancelable' => (string) ($validated['is_cancelable'] ?? 0),
            'cancelable_till' => $validated['cancelable_till'] ?? null,
            'allow_cod' => (string) ($validated['allow_cod'] ?? 1),
            'is_attachment_required' => (string) ($validated['is_attachment_required'] ?? 0),
            'attachment_mode' => $validated['attachment_mode'] ?? ProductAttachmentModeEnum::REQUIRED(),
            'featured' => (string) ($validated['featured'] ?? 0),
            'requires_otp' => (string) ($validated['requires_otp'] ?? 0),
            'video_type' => $validated['video_type'],
            'warranty_period' => $validated['warranty_period'] ?? null,
            'guarantee_period' => $validated['guarantee_period'] ?? null,
            'made_in' => $validated['made_in'] ?? null,
            'hsn_code' => $validated['hsn_code'] ?? null,
            'tags' => json_encode($validated['tags'] ?? []),
            'custom_fields' => $validated['custom_fields'] ?? null,
            'is_inclusive_tax' => (string) ($validated['is_inclusive_tax'] ?? 0),
            'metadata' => $validated['metadata'] ?? null,
        ]);
        $category = Category::findOrFail($validated['category_id']);
        app(ProductApprovalService::class)->applyInitial($product, $category);

        return $product;
    }

    private function isVariantProduct(array $validated): bool
    {
        return $validated['type'] === 'variant' && isset($validated['variants_json']);
    }

    private function processVariantProduct($product, array $validated, array $pricingData, string $mode, $request): void
    {
        $variantsData = json_decode($validated['variants_json'], true);
        $newVariantIds = [];
        $existingVariants = ($mode === 'update')
            ? $product->variants()->with('attributes')->get()
            : collect();
        $existingVariantIds = $existingVariants->pluck('id')->toArray();
        foreach ($variantsData as $variantData) {
            $variant = null;
            $createdVariant = false;
            $imageName = 'variant_image'.$variantData['id'];

            if ($mode === 'update' && is_numeric($variantData['id'] ?? null)) {
                $variant = $existingVariants->firstWhere('id', (int) $variantData['id']);
            }
            if ($mode === 'update' && ! $variant && ! empty($variantData['attributes'])) {
                $variant = $this->findMatchingVariant($existingVariants, $variantData, $newVariantIds);
            }
            if ($variant) {
                $variant->update([
                    'title' => ! empty($variantData['title']) ? $variantData['title'] : null,
                    'weight' => ! empty($variantData['weight']) ? (float) $variantData['weight'] : 1,
                    'height' => ! empty($variantData['height']) ? (float) $variantData['height'] : 1,
                    'breadth' => ! empty($variantData['breadth']) ? (float) $variantData['breadth'] : 1,
                    'length' => ! empty($variantData['length']) ? (float) $variantData['length'] : 1,
                    'availability' => (! empty($variantData['availability']) && $variantData['availability'] === 'no') ? false : true,
                    'barcode' => ! empty($variantData['barcode']) ? $variantData['barcode'] : null,
                    'visibility' => $variantData['visibility'] ?? 'published',
                    'is_default' => $variantData['is_default'] == 'on' ? true : false,
                ]);
                if (! empty($variantData['attributes']) && ! $this->variantAttributesMatch($variant, $variantData['attributes'])) {
                    ProductVariantAttribute::where('product_variant_id', $variant->id)->delete();
                    $this->createVariantAttributes($product->id, $variant->id, $variantData['attributes']);
                }
                $newVariantIds[] = $variant->id;
            } else {
                $createdVariant = true;
                $variant = ProductVariant::create([
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'title' => ! empty($variantData['title']) ? $variantData['title'] : null,
                    'weight' => ! empty($variantData['weight']) ? (float) $variantData['weight'] : 1,
                    'height' => ! empty($variantData['height']) ? (float) $variantData['height'] : 1,
                    'breadth' => ! empty($variantData['breadth']) ? (float) $variantData['breadth'] : 1,
                    'length' => ! empty($variantData['length']) ? (float) $variantData['length'] : 1,
                    'availability' => (! empty($variantData['availability']) && $variantData['availability'] === 'no') ? false : true,
                    'barcode' => ! empty($variantData['barcode']) ? $variantData['barcode'] : null,
                    'visibility' => $variantData['visibility'] ?? 'published',
                    'is_default' => $variantData['is_default'] == 'on' ? true : false,
                ]);

                if (! empty($variantData['attributes'])) {
                    $this->createVariantAttributes(productId: $product->id, variantId: $variant->id, attributes: $variantData['attributes']);
                }

                $newVariantIds[] = $variant->id;
            }
            if (! empty($variantData['remove_image'])) {
                $variant->clearMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
            } elseif ($request->hasFile($imageName)) {
                $variant->clearMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
                $this->handleVariantMediaUploads($variant, $imageName);
            } elseif ($createdVariant && ! empty($variantData['source_variant_id'])) {
                $sourceVariant = $existingVariants->firstWhere('id', (int) $variantData['source_variant_id']);
                $sourceVariant?->getFirstMedia(SpatieMediaCollectionName::VARIANT_IMAGE())
                    ?->copy($variant, SpatieMediaCollectionName::VARIANT_IMAGE());
            }
            $this->handleVariantPricing($variant, $variantData, $pricingData, $mode);
        }

        if ($mode === 'update' && ! empty($existingVariantIds)) {
            $variantsToDelete = array_diff($existingVariantIds, $newVariantIds);
            if (! empty($variantsToDelete)) {
                ProductVariantAttribute::whereIn('product_variant_id', $variantsToDelete)->delete();
                ProductVariant::whereIn('id', $variantsToDelete)->delete();
            }
        }
    }

    private function findMatchingVariant($existingVariants, $variantData, $alreadyMatchedIds)
    {
        $variantAttributeMap = [];
        foreach ($variantData['attributes'] as $attr) {
            $variantAttributeMap[$attr['attribute_id']] = $attr['value_id'];
        }

        foreach ($existingVariants as $existingVariant) {
            if (in_array($existingVariant->id, $alreadyMatchedIds)) {
                continue;
            }

            $existingAttributes = $existingVariant->attributes;

            if (count($existingAttributes) !== count($variantAttributeMap)) {
                continue;
            }

            $allMatch = true;
            foreach ($existingAttributes as $attr) {
                if (! isset($variantAttributeMap[$attr->global_attribute_id]) ||
                    $variantAttributeMap[$attr->global_attribute_id] != $attr->global_attribute_value_id) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return $existingVariant;
            }
        }

        return null;
    }

    private function variantAttributesMatch(ProductVariant $variant, array $attributes): bool
    {
        $existing = $variant->attributes
            ->map(fn ($attribute) => (int) $attribute->global_attribute_id.':'.(int) $attribute->global_attribute_value_id)
            ->sort()
            ->values()
            ->all();
        $submitted = collect($attributes)
            ->map(fn ($attribute) => (int) ($attribute['attribute_id'] ?? 0).':'.(int) ($attribute['value_id'] ?? 0))
            ->sort()
            ->values()
            ->all();

        return $existing === $submitted;
    }

    /**
     * Pricing + inventory writer for a single variant.
     *
     * Reads price/special_price/cost/sku from the variant payload (new shape)
     * and the per-location inventory rows from $variantData['inventory'].
     * Each inventory row: { store_location_id, sell_from(bool), tracked(bool), qty(int|null) }.
     *
     * Falls back to the legacy $pricingData['variant_pricing'] shape (one row
     * per (variant, store)) when the new fields aren't present — lets the old
     * form keep working while the rewrite ships in the next chunk; the first
     * matching legacy row's price/special_price/cost/sku populate the variant,
     * and its stock is laid down on each store's default location.
     */
    private function handleVariantPricing($variant, $variantData, $pricingData, $mode): void
    {
        $legacyRows = is_array($pricingData['variant_pricing'] ?? null)
            ? array_values(array_filter(
                $pricingData['variant_pricing'],
                fn ($vp) => isset($vp['variant_id']) && (string) $vp['variant_id'] === (string) ($variantData['id'] ?? '')
            ))
            : [];

        $pricingPayload = $this->resolvePricingPayload($variantData, $legacyRows);
        $this->applyVariantPricing($variant, $pricingPayload);

        $inventoryRows = $this->resolveInventoryRows($variantData, $legacyRows);
        $this->applyVariantInventory($variant, $inventoryRows, $mode);
    }

    private function processSimpleProduct($product, $validated, array $pricingData, string $mode): void
    {
        $variant = null;
        if ($mode === 'update') {
            $variant = $product->variants()->first();
        }
        $variantData = [
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'weight' => ! empty($validated['weight']) ? $validated['weight'] : '1',
            'height' => ! empty($validated['height']) ? $validated['height'] : '1',
            'breadth' => ! empty($validated['breadth']) ? $validated['breadth'] : '1',
            'length' => ! empty($validated['length']) ? $validated['length'] : '1',
            'barcode' => ! empty($validated['barcode']) ? $validated['barcode'] : '0',
            'availability' => 1,
            'visibility' => $variant?->visibility ?? 'published',
            'is_default' => true,
        ];

        if ($variant) {
            $variant->update($variantData);
        } else {
            $variant = ProductVariant::create($variantData);
        }
        $legacyRows = is_array($pricingData['store_pricing'] ?? null)
            ? array_values($pricingData['store_pricing'])
            : [];
        $simplePayload = $validated;
        $pricingPayload = $this->resolvePricingPayload($simplePayload, $legacyRows);
        $this->applyVariantPricing($variant, $pricingPayload);

        $inventoryRows = $this->resolveInventoryRows($simplePayload, $legacyRows);
        $this->applyVariantInventory($variant, $inventoryRows, $mode);
    }

    private function createVariantAttributes(int $productId, int $variantId, array $attributes): void
    {
        $sellerId = (int) (Product::whereKey($productId)->value('seller_id') ?? 0);

        foreach ($attributes as $attribute) {
            $attributeId = (int) ($attribute['attribute_id'] ?? 0);
            $valueId = (int) ($attribute['value_id'] ?? 0);

            $ownsAttribute = GlobalProductAttribute::whereKey($attributeId)
                ->where(fn ($q) => $q->where('seller_id', $sellerId)->orWhereNull('seller_id'))
                ->exists();

            $valueBelongs = GlobalProductAttributeValue::whereKey($valueId)
                ->where('global_attribute_id', $attributeId)
                ->exists();

            if (! $ownsAttribute || ! $valueBelongs) {
                throw new \InvalidArgumentException(__('labels.invalid_product_attribute'));
            }

            ProductVariantAttribute::create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'global_attribute_id' => $attributeId,
                'global_attribute_value_id' => $valueId,
            ]);
        }
    }

    /**
     * Normalise the new variant payload (price/special_price/cost/sku) and fall
     * back to the first legacy store-pricing row when the new fields aren't sent.
     * Returns an associative array always populated with the four keys; price/
     * special_price default to 0, cost to null, sku to null. `special_price`
     * defaults to `price` when zero or missing.
     */
    private function resolvePricingPayload(array $variantData, array $legacyRows): array
    {
        $priceProvided = array_key_exists('price', $variantData) && $variantData['price'] !== null && $variantData['price'] !== '';
        $price = $priceProvided ? (float) $variantData['price'] : null;
        $special = isset($variantData['special_price']) && $variantData['special_price'] !== '' ? (float) $variantData['special_price'] : null;
        $cost = isset($variantData['cost']) && $variantData['cost'] !== '' ? (float) $variantData['cost'] : null;
        $sku = isset($variantData['sku']) && $variantData['sku'] !== '' ? (string) $variantData['sku'] : null;

        if (! $priceProvided && ! empty($legacyRows)) {
            $first = $legacyRows[0];
            $price = isset($first['price']) ? (float) $first['price'] : 0.0;
            $special = isset($first['special_price']) && $first['special_price'] !== '' ? (float) $first['special_price'] : $price;
            $cost = isset($first['cost']) && $first['cost'] !== '' ? (float) $first['cost'] : null;
            $sku = $sku ?? ($first['sku'] ?? null);
        }

        if ($price === null) {
            $price = 0.0;
        }
        if ($special === null || $special <= 0) {
            $special = $price;
        }

        return [
            'price' => $price,
            'special_price' => $special,
            'cost' => $cost,
            'sku' => $sku,
        ];
    }

    /**
     * Push price/special_price/cost/sku onto the variant. Uses the raw column
     * names so the tax-adjustment accessor doesn't double-tax on save.
     */
    private function applyVariantPricing(ProductVariant $variant, array $pricing): void
    {
        $variant->forceFill([
            'price' => $pricing['price'],
            'special_price' => $pricing['special_price'],
            'cost' => $pricing['cost'],
            'sku' => $pricing['sku'],
        ])->save();
    }

    /**
     * Build a normalised list of inventory rows.
     *
     * New shape (preferred) — pulled from $variantData['inventory']:
     *   [{ store_location_id, sell_from(bool), tracked(bool), qty(int|null) }, ...]
     *
     * Legacy fallback — for each legacy (variant,store) pricing row, lay the
     * stock down on that store's default location with sell_from = true and
     * tracked = (stock > 0).
     */
    private function resolveInventoryRows(array $variantData, array $legacyRows): array
    {
        $rows = [];

        if (! empty($variantData['inventory']) && is_array($variantData['inventory'])) {
            foreach ($variantData['inventory'] as $r) {
                if (empty($r['store_location_id'])) {
                    continue;
                }
                $rows[] = [
                    'store_location_id' => (int) $r['store_location_id'],
                    'is_fulfillment_location' => ! empty($r['sell_from']) || ! empty($r['is_fulfillment_location']),
                    'tracked' => ! empty($r['tracked']),
                    'stock' => isset($r['qty']) ? (int) $r['qty'] : (isset($r['stock']) ? (int) $r['stock'] : null),
                ];
            }

            return $rows;
        }

        if (empty($legacyRows)) {
            return $rows;
        }

        $storeIds = array_values(array_unique(array_map(fn ($r) => (int) ($r['store_id'] ?? 0), $legacyRows)));
        $defaultByStore = StoreLocation::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_default', true)
            ->pluck('id', 'store_id');

        foreach ($legacyRows as $r) {
            $sid = (int) ($r['store_id'] ?? 0);
            $locationId = $defaultByStore[$sid] ?? null;
            if (! $locationId) {
                continue;
            }
            $stock = isset($r['stock']) ? (int) $r['stock'] : 0;
            $rows[] = [
                'store_location_id' => $locationId,
                'is_fulfillment_location' => true,
                'tracked' => $stock > 0,
                'stock' => $stock,
            ];
        }

        return $rows;
    }

    /**
     * Upsert inventory rows for a variant, then sync the store_product_variants
     * mapping so "store X carries this variant" stays derived from the locations
     * the seller actually picked.
     *
     * On update: replaces the full set (delete-then-insert) so removed locations
     * actually disappear from the seller's selection.
     */
    private function applyVariantInventory(ProductVariant $variant, array $rows, string $mode): void
    {
        $existingStock = StoreLocationInventory::query()
            ->where('product_variant_id', $variant->id)
            ->pluck('stock', 'store_location_id');

        if ($mode === 'update') {
            StoreLocationInventory::where('product_variant_id', $variant->id)->delete();
        }

        if (empty($rows)) {
            $this->syncStoreProductVariantMapping($variant->id, []);

            return;
        }

        $locationIds = array_values(array_unique(array_column($rows, 'store_location_id')));
        $locationStoreMap = StoreLocation::query()
            ->whereIn('id', $locationIds)
            ->pluck('store_id', 'id');

        $now = now();
        $inventoryPayload = [];
        $storeIds = [];

        foreach ($rows as $r) {
            $locId = (int) $r['store_location_id'];
            if (! isset($locationStoreMap[$locId])) {
                continue;
            }
            $inventoryPayload[] = [
                'store_location_id' => $locId,
                'product_variant_id' => $variant->id,
                'is_fulfillment_location' => (bool) $r['is_fulfillment_location'],
                'tracked' => (bool) $r['tracked'],
                'stock' => (int) ($r['stock'] ?? $existingStock[$locId] ?? 0),
                'low_stock_threshold' => 0,
                'reserved' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $storeIds[] = (int) $locationStoreMap[$locId];
        }

        if (! empty($inventoryPayload)) {
            StoreLocationInventory::insert($inventoryPayload);
        }

        $this->syncStoreProductVariantMapping($variant->id, array_values(array_unique($storeIds)));
    }

    /**
     * Make sure store_product_variants has exactly one row per store that has
     * at least one inventory row for this variant. The table is just a mapping
     * now (no pricing/stock), so we can recreate it cheaply.
     */
    private function syncStoreProductVariantMapping(int $variantId, array $storeIds): void
    {
        StoreProductVariant::where('product_variant_id', $variantId)->forceDelete();
        if (empty($storeIds)) {
            return;
        }

        $now = now();
        StoreProductVariant::insert(array_map(fn ($sid) => [
            'product_variant_id' => $variantId,
            'store_id' => $sid,
            'created_at' => $now,
            'updated_at' => $now,
        ], $storeIds));
    }

    private function updateProductDetails(Product $product, array $validated): void
    {
        $product->update([
            'type' => $validated['type'] ?? $product->type,
            'status' => $validated['status'] ?? $product->status,
            'seller_id' => $validated['seller_id'] ?? $product->seller_id,
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'title' => $validated['title'],
            'slug' => $validated['handle'] ?? $product->slug,
            'base_prep_time' => $validated['base_prep_time'] ?? 0,
            'short_description' => $validated['short_description'],
            'description' => $validated['description'],
            'indicator' => $validated['indicator'] ?? null,
            'image_fit' => $validated['image_fit'] ?? $product->image_fit,
            'hsn_code' => $validated['hsn_code'] ?? null,
            'minimum_order_quantity' => $validated['minimum_order_quantity'] ?? 1,
            'quantity_step_size' => $validated['quantity_step_size'] ?? 1,
            'total_allowed_quantity' => $validated['total_allowed_quantity'] ?? null,
            'is_returnable' => (string) ($validated['is_returnable'] ?? 0),
            'returnable_days' => $validated['returnable_days'] ?? null,
            'is_cancelable' => (string) ($validated['is_cancelable'] ?? 0),
            'cancelable_till' => $validated['cancelable_till'] ?? null,
            'allow_cod' => (string) ($validated['allow_cod'] ?? 1),
            'is_attachment_required' => (string) ($validated['is_attachment_required'] ?? 0),
            'attachment_mode' => $validated['attachment_mode'] ?? $product->attachment_mode ?? ProductAttachmentModeEnum::REQUIRED(),
            'featured' => (string) ($validated['featured'] ?? 0),
            'requires_otp' => array_key_exists('requires_otp', $validated)
                ? (string) $validated['requires_otp']
                : (string) $product->requires_otp,
            'video_type' => $validated['video_type'],
            'warranty_period' => $validated['warranty_period'] ?? null,
            'guarantee_period' => $validated['guarantee_period'] ?? null,
            'made_in' => $validated['made_in'] ?? null,
            'tags' => json_encode($validated['tags'] ?? []),
            'custom_fields' => $validated['custom_fields'] ?? null,
            'is_inclusive_tax' => (string) ($validated['is_inclusive_tax'] ?? 0),
            'metadata' => $validated['metadata'] ?? $product->metadata,
        ]);
        $product->save();
    }

    /**
     * Clean up all variants and their related records for a product.
     * Used when switching from variant product to simple product during update.
     */
    private function cleanupAllVariants(Product $product): void
    {
        $variantIds = $product->variants()->pluck('id')->toArray();
        if (empty($variantIds)) {
            return;
        }
        StoreProductVariant::whereIn('product_variant_id', $variantIds)->forceDelete();
        ProductVariantAttribute::whereIn('product_variant_id', $variantIds)->forceDelete();
        ProductVariant::whereIn('id', $variantIds)->forceDelete();
    }

    private function handleVariantMediaUploads($variant, $payload_image): void
    {
        $variant->addMediaFromRequest($payload_image)->toMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
    }

    private function handleMediaUploads($product, $request, bool $mainImageReplacement): void
    {
        if ($mainImageReplacement) {
            $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
            SpatieMediaService::upload(model: $product, media: SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
        }

        if ($request->hasFile('additional_images')) {
            $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());

            foreach ($request->file('additional_images') as $image) {
                SpatieMediaService::uploadFromRequest($product, $image, SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());
            }
        }

        if (ProductVideoTypeEnum::LOCAL() === $request->video_type) {
            if ($request->hasFile('product_video')) {
                $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_VIDEO());
                SpatieMediaService::upload(model: $product, media: SpatieMediaCollectionName::PRODUCT_VIDEO());
            }
        } else {
            $product->update(['video_link' => $request['video_link']]);
        }
    }

    private function shouldReplaceMainImage(Product $product, $request): bool
    {
        if (! $request->hasFile('main_image')) {
            return false;
        }

        if (! $request->exists('main_image_changed')) {
            return true;
        }

        return $request->boolean('main_image_changed')
            || ! $product->hasMedia(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
    }

    private function isValidLocalImage(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (! in_array($ext, $allowed, true)) {
            return false;
        }
        $size = @filesize($path);
        if ($size === false) {
            return false;
        }

        return $size <= 2 * 1024 * 1024;
    }

    public function setMainImageFromPath(Product $product, string $path): void
    {
        if (! $this->isValidLocalImage($path)) {
            $imageName = basename($path);
            throw new \InvalidArgumentException("Main image '{$imageName}' must be jpg, jpeg, png or webp and not exceed 2MB.");
        }
        $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
        $product->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
    }

    public function setAdditionalImagesFromPaths(Product $product, array $paths): void
    {
        $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());
        if (empty($paths)) {
            return;
        }
        foreach ($paths as $p) {
            if (! $this->isValidLocalImage($p)) {
                continue;
            }
            $product->addMedia($p)
                ->preservingOriginal()
                ->toMediaCollection(SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());
        }
    }

    public function setVariantImageFromPath(ProductVariant $variant, string $path): void
    {
        if (! $this->isValidLocalImage($path)) {
            $imageName = basename($path);
            throw new \InvalidArgumentException("Variant image '{$imageName}' must be jpg, jpeg, png or webp and not exceed 2MB.");
        }
        $variant->clearMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
        $variant->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
    }
}
