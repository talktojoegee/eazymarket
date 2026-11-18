<?php

namespace App\Http\Requests\Product;

use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Product\ProductAttachmentModeEnum;
use App\Enums\Product\ProductImageFitEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Market;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreUpdateProductRequest extends FormRequest
{
    protected ?array $sellerStoreIds = null;
    protected ?array $sellerLocationIds = null;
    protected ?array $sellerTaxClassIds = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $selectedStoreId = (int) $this->attributes->get('seller_store')?->id;
        if ($selectedStoreId > 0 && $this->input('type') === ProductTypeEnum::KIT->value) {
            $this->merge(['kit_store_id' => $selectedStoreId]);
        }

        $variants = json_decode((string) $this->input('variants_json'), true);
        if (! is_array($variants)) {
            return;
        }

        foreach ($variants as &$variant) {
            $attributes = $variant['attributes'] ?? [];
            if (! is_array($attributes)) {
                continue;
            }

            if (! array_is_list($attributes)) {
                $attributes = collect($attributes)
                    ->map(fn ($valueId, $attributeId) => [
                        'attribute_id' => $attributeId,
                        'value_id' => $valueId,
                    ])
                    ->values()
                    ->all();
            }

            $variant['attributes'] = array_map(fn ($attribute) => is_array($attribute) ? [
                'attribute_id' => $attribute['attribute_id'] ?? $attribute['global_attribute_id'] ?? null,
                'value_id' => $attribute['value_id'] ?? $attribute['global_attribute_value_id'] ?? null,
            ] : ['attribute_id' => null, 'value_id' => null], $attributes);
        }
        unset($variant);

        $this->merge(['variants_json' => json_encode($variants)]);
    }

    public function rules(): array
    {
        return [
            'seller_id' => [
                $this->isAdminPanelRequest() ? 'required' : 'nullable',
                'integer',
                'exists:sellers,id',
            ],
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'tax_groups' => 'nullable|array',
            'tax_groups.*' => 'integer|exists:tax_classes,id',
            'type' => ['required', new Enum(ProductTypeEnum::class)],
            'kit_store_id' => ['required_if:type,kit', 'nullable', 'integer', 'exists:stores,id'],
            'kit_components' => ['required_if:type,kit', 'array', 'min:1', 'max:30'],
            'kit_components.*.product_variant_id' => ['required_if:type,kit', 'integer', 'distinct', 'exists:product_variants,id'],
            'kit_components.*.quantity' => ['required_if:type,kit', 'integer', 'min:1', 'max:999'],
            'short_description' => 'required|string|max:255',
            'description' => 'required|string|max:65535',
            'indicator' => 'nullable|string|max:255',
            'image_fit' => ['required', new Enum(ProductImageFitEnum::class)],
            'minimum_order_quantity' => 'required|integer|min:1',
            'quantity_step_size' => 'required|integer|min:1',
            'total_allowed_quantity' => 'required|integer|min:0',
            'is_returnable' => 'nullable|boolean',
            'returnable_days' => 'required_if:is_returnable,1|nullable|integer|min:0',
            'is_cancelable' => 'nullable|boolean',
            'cancelable_till' => 'required_if:is_cancelable,1|nullable|in:'.implode(',', array_map('strval', FulfillmentStatusEnum::cancellableTillOptions())),
            'allow_cod' => 'nullable|boolean',
            'is_attachment_required' => 'nullable|boolean',
            'attachment_mode' => ['nullable', new Enum(ProductAttachmentModeEnum::class)],
            'featured' => 'nullable|boolean',
            'requires_otp' => 'nullable|boolean',
            'video_type' => 'nullable|string',
            'video_link' => 'nullable|string|url',
            'warranty_period' => 'nullable|string',
            'guarantee_period' => 'nullable|string',
            'made_in' => 'nullable|string',
            'hsn_code' => ['nullable', 'regex:/^\d{4}(\d{2}(\d{2})?)?$/'],
            'main_image' => ($this->isMethod('POST') && ! $this->route('id') && empty($this->input('image_src')) ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|max:2048',
            'main_image_changed' => 'nullable|boolean',
            'additional_images' => 'nullable|array|max:20',
            'additional_images.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'product_video' => 'nullable|file|mimes:mp4,mov,avi|max:20480',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'custom_fields.*' => 'nullable|string|max:255',
            'custom_sections' => 'nullable|array',
            'custom_sections.*.id' => 'nullable|integer|exists:custom_product_sections,id',
            'custom_sections.*.title' => 'required_with:custom_sections|string|max:255',
            'custom_sections.*.description' => 'nullable|string',
            'custom_sections.*.sort_order' => 'nullable|integer|min:0',
            'custom_sections.*.fields' => 'nullable|array',
            'custom_sections.*.fields.*.id' => 'nullable|integer|exists:custom_product_fields,id',
            'custom_sections.*.fields.*.title' => 'required_without:custom_sections.*.fields.*.id|string|max:255',
            'custom_sections.*.fields.*.description' => 'nullable|string',
            'custom_sections.*.fields.*.sort_order' => 'nullable|integer|min:0',
            'custom_sections.*.fields.*.image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'is_inclusive_tax' => 'nullable|boolean',
            'pricing' => 'required|json',
            'variants_json' => 'required_if:type,variant|json',

            'price' => 'nullable|numeric|min:0.01',
            'special_price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'inventory' => 'nullable|array',
            'inventory.*.store_location_id' => 'nullable|integer|exists:store_locations,id',
            'inventory.*.sell_from' => 'nullable',
            'inventory.*.tracked' => 'nullable',
            'inventory.*.qty' => 'nullable|integer|min:0',

            'custom_sections_json' => 'nullable|json',
            'weight' => 'nullable|min:0',
            'height' => 'nullable|min:0',
            'length' => 'nullable|min:0',
            'breadth' => 'nullable|min:0',
            'barcode' => 'required_if:type,simple,kit|nullable|string',
            'metadata' => 'nullable|array',
            'metadata.seo_title' => 'nullable|string|max:255',
            'metadata.seo_keywords' => 'nullable|array',
            'metadata.seo_keywords.*' => 'nullable|string|max:60',
            'metadata.seo_description' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('labels.product_title'),
            'category_id' => __('labels.category'),
            'brand_id' => __('labels.brand'),
            'type' => __('labels.product_type'),
            'image_fit' => __('labels.image_fit'),
            'short_description' => __('labels.short_description'),
            'description' => __('labels.description'),
            'main_image' => __('labels.main_image'),
            'additional_images.*' => __('labels.additional_image'),
            'pricing' => __('labels.pricing_information'),
            'variants_json' => __('labels.variants_information'),
            'custom_sections_json' => __('labels.custom_sections'),
            'custom_sections' => __('labels.custom_sections'),
            'custom_sections.*.title' => __('labels.section_title'),
            'custom_sections.*.description' => __('labels.section_description'),
            'custom_sections.*.sort_order' => __('labels.section_sort_order'),
            'attachment_mode' => __('labels.attachment_mode'),
            'custom_sections.*.fields.*.title' => __('labels.field_title'),
            'custom_sections.*.fields.*.description' => __('labels.field_description'),
            'custom_sections.*.fields.*.sort_order' => __('labels.field_sort_order'),
            'custom_sections.*.fields.*.image' => __('labels.field_image'),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('labels.title_required'),
            'category_id.required' => __('labels.category_id_required'),
            'type.required' => __('labels.type_required'),
            'main_image.required' => __('labels.main_image_required'),
            'pricing.required' => __('labels.pricing_required'),
            'pricing.json' => __('labels.pricing_json'),
            'variants_json.required_if' => __('labels.variants_json_required_if'),
            'custom_sections_json.json' => __('labels.invalid_json'),
            'returnable_days.required_if' => __('labels.returnable_days_required_if'),
            'cancelable_till.required_if' => __('labels.cancelable_till_required_if'),
            'hsn_code.regex' => __('labels.hsn_code_digits_only'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateQuantityLogic($validator);

            $type = $this->input('type');
            $isVariant = $type == ProductTypeEnum::VARIANT() || $type == ProductTypeEnum::VARIANT;

            $this->validateNewPayload($validator, $isVariant);

            $this->validateTaxGroupsScope($validator);
        });
    }

    /**
     * Tax classes attached to the product must belong to a market the
     * seller's stores are enabled in. Same scope rule as the Tax multi-select
     * in the form: union of markets across the seller's stores.
     */
    protected function validateTaxGroupsScope($validator): void
    {
        $submitted = (array) $this->input('tax_groups', []);
        $submitted = array_values(array_filter(array_map('intval', $submitted)));
        if (empty($submitted)) {
            return;
        }
        if (! $this->isSellerRequest()) {
            return;
        }
        $allowed = $this->getSellerTaxClassIds();
        foreach ($submitted as $id) {
            if (! in_array($id, $allowed, true)) {
                $validator->errors()->add('tax_groups', __('labels.tax_class_not_in_seller_markets'));
                return;
            }
        }
    }

    /**
     * Cache + return tax_class IDs belonging to the seller's stores' market
     * union. Empty if the seller has no stores or no markets enabled.
     */
    protected function getSellerTaxClassIds(): array
    {
        if ($this->sellerTaxClassIds !== null) {
            return $this->sellerTaxClassIds;
        }
        $storeIds = $this->getSellerStoreIds();
        if (empty($storeIds)) {
            return $this->sellerTaxClassIds = [];
        }
        $marketIds = Market::query()
            ->whereHas('stores', fn ($q) => $q->whereIn('stores.id', $storeIds))
            ->pluck('id')
            ->all();
        if (empty($marketIds)) {
            return $this->sellerTaxClassIds = [];
        }
        return $this->sellerTaxClassIds = TaxClass::query()
            ->whereIn('market_id', $marketIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Detect whether the request carries the new variant-pricing payload.
     * Cheap heuristic — any variant (or simple product top-level) that has a
     * `price` field is treated as the new shape.
     */
    protected function hasNewPayloadShape(bool $isVariant): bool
    {
        if ($isVariant) {
            $variants = json_decode((string) $this->input('variants_json'), true) ?? [];
            foreach ($variants as $v) {
                if (array_key_exists('price', $v) || array_key_exists('inventory', $v)) {
                    return true;
                }
            }
            return false;
        }

        return $this->filled('price') || $this->has('inventory');
    }

    /**
     * Validation entry point for the new payload shape.
     *
     *   Variant products: each row in `variants_json` carries
     *     { id, title, attributes, weight/height/.../barcode, price,
     *       special_price, cost, sku, is_default, availability, inventory[] }
     *   Simple products: same fields at top level (no variants_json).
     *
     * inventory[]: [{ store_location_id, sell_from(bool), tracked(bool), qty(int|null) }]
     *   - at least one row with sell_from = true is required
     *   - every store_location_id must belong to a store the seller owns
     *   - qty required when tracked = true
     */
    protected function validateNewPayload($validator, bool $isVariant): void
    {
        $sellerStoreIds = $this->isSellerRequest() ? $this->getSellerStoreIds() : null;
        $sellerLocationIds = $this->isSellerRequest() ? $this->getSellerLocationIds() : null;

        if ($this->isSellerRequest() && empty($sellerStoreIds)) {
            $validator->errors()->add('pricing', __('labels.seller_store_pricing_required_for_product'));
            return;
        }

        if ($isVariant) {
            $variants = json_decode((string) $this->input('variants_json'), true) ?? [];
            if (empty($variants)) {
                $validator->errors()->add('variants_json', __('labels.variant_required'));
                return;
            }
            $this->validateVariantBarcodeUniqueness($validator, $variants);
            $this->validateVariantMatrix($validator, $variants);

            $isDefaultSet = false;
            foreach ($variants as $i => $variant) {
                if ($this->validateVariantStructure($validator, $variant, $i)) continue;
                $this->validateVariantPricingFields($validator, $variant, "variants_json.$i");
                $this->validateInventoryRows($validator, $variant['inventory'] ?? [], "variants_json.$i.inventory", $sellerLocationIds);

                if (isset($variant['is_default']) && ($variant['is_default'] === 'on' || $variant['is_default'] === true || $variant['is_default'] === 1)) {
                    $isDefaultSet = true;
                }
            }
            if (! $isDefaultSet) {
                $validator->errors()->add('variants_json', __('labels.default_variant_required'));
            }
            return;
        }

        $payload = $this->all();
        $this->validateVariantPricingFields($validator, $payload, 'pricing');
        $inventoryRows = $this->input('inventory', []);
        $this->validateInventoryRows($validator, $inventoryRows, 'inventory', $sellerLocationIds);
    }

    protected function validateVariantMatrix($validator, array $variants): void
    {
        if (count($variants) > 100) {
            $validator->errors()->add('variants_json', __('labels.variant_limit_exceeded', ['limit' => 100]));
            return;
        }

        $sellerId = (int) ($this->resolveSellerIdForScope() ?? 0);
        $expectedDimensions = null;
        $combinationKeys = [];
        $attributeIds = [];
        $attributeUsages = [];
        $valuePairs = [];

        foreach ($variants as $variantIndex => $variant) {
            $attributes = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];
            $dimensions = [];
            $pairs = [];

            foreach ($attributes as $attributeIndex => $attribute) {
                $attributeId = (int) ($attribute['attribute_id'] ?? 0);
                $valueId = (int) ($attribute['value_id'] ?? 0);
                if ($attributeId <= 0 || $valueId <= 0) {
                    $validator->errors()->add("variants_json.$variantIndex.attributes.$attributeIndex", __('labels.invalid_product_attribute'));
                    continue;
                }
                if (in_array($attributeId, $dimensions, true)) {
                    $validator->errors()->add("variants_json.$variantIndex.attributes.$attributeIndex", __('labels.duplicate_product_attribute'));
                    continue;
                }
                $dimensions[] = $attributeId;
                $pairs[] = $attributeId.':'.$valueId;
                $attributeIds[] = $attributeId;
                $attributeUsages[$attributeId][] = [
                    'variant_id' => (int) ($variant['id'] ?? 0),
                    'value_id' => $valueId,
                ];
                $valuePairs[$valueId] = $attributeId;
            }

            sort($dimensions);
            sort($pairs);
            if ($expectedDimensions === null) {
                $expectedDimensions = $dimensions;
            } elseif ($dimensions !== $expectedDimensions) {
                $validator->errors()->add("variants_json.$variantIndex.attributes", __('labels.variant_dimension_mismatch'));
            }

            $key = implode('|', $pairs);
            if ($key !== '' && isset($combinationKeys[$key])) {
                $validator->errors()->add("variants_json.$variantIndex.attributes", __('labels.duplicate_variant_combination'));
            }
            $combinationKeys[$key] = true;

            if (! empty($variant['source_variant_id'])) {
                $productId = (int) ($this->route('id') ?? 0);
                $validSource = $productId > 0 && ProductVariant::query()
                    ->whereKey((int) $variant['source_variant_id'])
                    ->where('product_id', $productId)
                    ->whereHas('product', fn ($query) => $query->where('seller_id', $sellerId))
                    ->exists();
                if (! $validSource) {
                    $validator->errors()->add("variants_json.$variantIndex.source_variant_id", __('labels.invalid_source_variant'));
                }
            }
        }

        $ownedAttributeIds = GlobalProductAttribute::query()
            ->whereIn('id', array_unique($attributeIds))
            ->where(fn ($query) => $query->where('seller_id', $sellerId)->orWhereNull('seller_id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missingAttributeIds = array_values(array_diff(array_unique($attributeIds), $ownedAttributeIds));
        $productId = (int) ($this->route('id') ?? 0);
        $historicalValuePairs = [];
        if ($productId > 0 && $missingAttributeIds) {
            $variantIds = collect($attributeUsages)
                ->flatten(1)
                ->pluck('variant_id')
                ->filter()
                ->unique()
                ->all();
            $historicalAssignments = ProductVariantAttribute::query()
                ->where('product_id', $productId)
                ->whereIn('product_variant_id', $variantIds)
                ->whereIn('global_attribute_id', $missingAttributeIds)
                ->get(['product_variant_id', 'global_attribute_id', 'global_attribute_value_id'])
                ->mapWithKeys(fn (ProductVariantAttribute $assignment) => [
                    $assignment->product_variant_id.':'.$assignment->global_attribute_id.':'.$assignment->global_attribute_value_id => true,
                ]);

            foreach ($missingAttributeIds as $attributeId) {
                $allUsagesAreExisting = collect($attributeUsages[$attributeId] ?? [])->every(
                    fn (array $usage) => $usage['variant_id'] > 0
                        && $historicalAssignments->has($usage['variant_id'].':'.$attributeId.':'.$usage['value_id'])
                );
                if ($allUsagesAreExisting) {
                    $ownedAttributeIds[] = $attributeId;
                    foreach ($attributeUsages[$attributeId] as $usage) {
                        $historicalValuePairs[$attributeId.':'.$usage['value_id']] = true;
                    }
                }
            }
        }

        foreach (array_unique($attributeIds) as $attributeId) {
            if (! in_array($attributeId, $ownedAttributeIds, true)) {
                $validator->errors()->add('variants_json', __('labels.invalid_product_attribute'));
                break;
            }
        }

        if ($valuePairs) {
            $validValues = GlobalProductAttributeValue::query()
                ->whereIn('id', array_keys($valuePairs))
                ->get(['id', 'global_attribute_id'])
                ->keyBy('id');
            foreach ($valuePairs as $valueId => $attributeId) {
                $valueBelongsToAttribute = (int) ($validValues->get($valueId)?->global_attribute_id ?? 0) === (int) $attributeId;
                if (! $valueBelongsToAttribute && ! isset($historicalValuePairs[$attributeId.':'.$valueId])) {
                    $validator->errors()->add('variants_json', __('labels.invalid_product_attribute'));
                    break;
                }
            }
        }
    }

    /**
     * Per-variant structure checks reused from the legacy validator (title,
     * attributes, barcode, image). Returns true if validation should skip
     * pricing/inventory for this variant (e.g. core fields missing).
     */
    protected function validateVariantStructure($validator, array $variant, int $i): bool
    {
        if (empty($variant['attributes'])) {
            $validator->errors()->add("variants_json.$i", __('labels.attributes_required'));
            return true;
        }
        if (empty($variant['title'])) {
            $validator->errors()->add("variants_json.$i", __('labels.variant_title_required'));
            return true;
        }
        if (empty($variant['barcode'])) {
            $validator->errors()->add("variants_json.$i", ($variant['title'] ?? '').' '.__('labels.variant_barcode_required'));
            return true;
        }
        return false;
    }

    /**
     * Validate the four pricing fields on a variant (or simple) payload.
     *   - price required, numeric, >= 0
     *   - special_price optional but if present must be numeric and <= price
     *     (the project's convention — special_price is the actual selling
     *     price; equality means no discount)
     *   - cost optional, numeric, >= 0
     *   - sku required, string
     */
    protected function validateVariantPricingFields($validator, array $row, string $prefix): void
    {
        if (! isset($row['price']) || ! is_numeric($row['price']) || (float) $row['price'] <= 0) {
            $validator->errors()->add("$prefix.price", __('labels.price_required_numeric'));
        }

        $hasSpecial = isset($row['special_price']) && $row['special_price'] !== '' && $row['special_price'] !== null;
        if ($hasSpecial) {
            if (! is_numeric($row['special_price']) || (float) $row['special_price'] < 0) {
                $validator->errors()->add("$prefix.special_price", __('labels.special_price_numeric_non_negative'));
            } elseif (isset($row['price']) && is_numeric($row['price']) && (float) $row['special_price'] > (float) $row['price']) {
                $validator->errors()->add("$prefix.special_price", __('labels.special_price_less_than_price'));
            }
        }

        if (isset($row['cost']) && $row['cost'] !== '' && (! is_numeric($row['cost']) || (float) $row['cost'] < 0)) {
            $validator->errors()->add("$prefix.cost", __('labels.cost_required_numeric'));
        }

        if (empty($row['sku'])) {
            $validator->errors()->add("$prefix.sku", __('labels.sku_required'));
        }
    }

    /**
     * Per-(variant, store_location) inventory validation.
     */
    protected function validateInventoryRows($validator, $rows, string $prefix, ?array $sellerLocationIds): void
    {
        if (! is_array($rows) || empty($rows)) {
            $validator->errors()->add($prefix, __('labels.inventory_required'));
            return;
        }

        $hasSellFrom = false;
        foreach ($rows as $i => $r) {
            if (! is_array($r) || empty($r['store_location_id']) || ! is_numeric($r['store_location_id'])) {
                $validator->errors()->add("$prefix.$i.store_location_id", __('labels.store_location_required'));
                continue;
            }
            $locId = (int) $r['store_location_id'];
            if ($sellerLocationIds !== null && ! in_array($locId, $sellerLocationIds)) {
                $validator->errors()->add("$prefix.$i.store_location_id", __('labels.invalid_store_location_for_seller'));
                continue;
            }
            $sellFrom = ! empty($r['sell_from']);
            $tracked = ! empty($r['tracked']);
            if ($sellFrom) {
                $hasSellFrom = true;
            }
            if ($tracked && (! isset($r['qty']) || ! is_numeric($r['qty']) || (int) $r['qty'] < 0)) {
                $validator->errors()->add("$prefix.$i.qty", __('labels.stock_required_non_negative'));
            }
        }

        if (! $hasSellFrom) {
            $validator->errors()->add($prefix, __('labels.at_least_one_sell_from_location_required'));
        }
    }

    /**
     * Cache + return store_location IDs the current seller owns. Used by
     * inventory validation to ensure the seller can't post a location_id
     * that belongs to someone else's store.
     */
    protected function getSellerLocationIds(): array
    {
        if ($this->sellerLocationIds !== null) {
            return $this->sellerLocationIds;
        }
        $storeIds = $this->getSellerStoreIds();
        if (empty($storeIds)) {
            return $this->sellerLocationIds = [];
        }
        return $this->sellerLocationIds = StoreLocation::query()
            ->whereIn('store_id', $storeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Validate quantity-related fields.
     */
    protected function validateQuantityLogic($validator): void
    {
        $minQty = $this->input('minimum_order_quantity');
        $stepQty = $this->input('quantity_step_size');
        $totalQty = $this->input('total_allowed_quantity');

        $minQty = $minQty !== null && $minQty !== '' ? (int) $minQty : null;
        $stepQty = $stepQty !== null && $stepQty !== '' ? (int) $stepQty : null;
        $totalQty = $totalQty !== null && $totalQty !== '' ? (int) $totalQty : null;

        $hasFiniteMax = $totalQty !== null && $totalQty > 0;

        if ($hasFiniteMax && $minQty !== null && $minQty > $totalQty) {
            $validator->errors()->add('minimum_order_quantity', __('messages.minimum_order_quantity_lte_total_allowed_quantity'));
        }

        if ($hasFiniteMax && $stepQty !== null && $stepQty > $totalQty) {
            $validator->errors()->add('quantity_step_size', __('messages.quantity_step_size_lte_total_allowed_quantity'));
        }

        if ($hasFiniteMax && $minQty !== null && $stepQty !== null && $stepQty > 0 && $totalQty >= $minQty) {
            if ((($totalQty - $minQty) % $stepQty) !== 0) {
                $validator->errors()->add('total_allowed_quantity', __('messages.total_allowed_quantity_must_be_reachable_from_minimum', [
                    'min' => $minQty,
                    'step' => $stepQty,
                ]));
            }
        }
    }

    /**
     * Validate pricing JSON structure.
     */
    protected function validatePricingJsonStructure($validator): ?array
    {
        $pricingJson = $this->input('pricing');
        $pricingArr = json_decode($pricingJson, true);

        if (! is_array($pricingArr)) {
            $validator->errors()->add('pricing', __('labels.pricing_json_object'));

            return null;
        }

        return $pricingArr;
    }

    /**
     * Ensure submitted pricing contains at least one store owned by the seller.
     */
    protected function validateSellerStorePricing($validator, array $pricingArr): void
    {
        if (! $this->isSellerRequest()) {
            return;
        }

        $sellerStoreIds = $this->getSellerStoreIds();
        if (empty($sellerStoreIds)) {
            $validator->errors()->add('pricing', __('labels.seller_store_pricing_required_for_product'));

            return;
        }

        $pricingRows = [];
        if (isset($pricingArr['store_pricing']) && is_array($pricingArr['store_pricing'])) {
            $pricingRows = array_merge($pricingRows, $pricingArr['store_pricing']);
        }

        if (isset($pricingArr['variant_pricing']) && is_array($pricingArr['variant_pricing'])) {
            $pricingRows = array_merge($pricingRows, $pricingArr['variant_pricing']);
        }

        $hasSellerStorePricing = collect($pricingRows)
            ->contains(fn ($row) => isset($row['store_id']) && is_numeric($row['store_id']) && in_array((int) $row['store_id'], $sellerStoreIds, true));

        if (! $hasSellerStorePricing) {
            $validator->errors()->add('pricing', __('labels.seller_store_pricing_required_for_product'));
        }
    }

    /**
     * Validate variant product pricing and details.
     */
    protected function validateVariantProduct($validator, array $pricingArr): void
    {
        if (! isset($pricingArr['variant_pricing']) || ! is_array($pricingArr['variant_pricing'])) {
            $validator->errors()->add('pricing', __('labels.variant_pricing_array'));

            return;
        }

        $variantPricingArr = $pricingArr['variant_pricing'];
        $variants = json_decode($this->input('variants_json'), true) ?? [];
        $variantIds = array_column($variantPricingArr, 'variant_id') ?? [];
        $isDefaultSet = false;

        $this->validateVariantBarcodeUniqueness($validator, $variants);
        if (empty($variants)) {
            $validator->errors()->add('variants_json', __('labels.variant_required'));
        }

        foreach ($variants as $variant) {
            if ($this->validateVariantDetails($validator, $variant, $variantIds)) {
                return;
            }
            if ($variant['is_default'] == 'on') {
                $isDefaultSet = true;
            }
        }

        foreach ($variantPricingArr as $index => $row) {
            $this->validatePricingRow($validator, $row, "variant_pricing.$index");
        }

        if (! $isDefaultSet) {
            $validator->errors()->add('variants_json', __('labels.default_variant_required'));
        }
    }

    /**
     * Validate individual variant details.
     *
     * @return bool Returns true if validation should stop
     */
    protected function validateVariantDetails($validator, array $variant, array $variantIds): bool
    {
        if (! empty($this->file('variant_image'.$variant['id']))) {
            $variantImage = $this->file('variant_image'.$variant['id']);
            if (! in_array(strtolower($variantImage->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'])) {
                $validator->errors()->add('variants_json', __('labels.variant_image_file_type', ['variant' => $variant['title']]));

                return true;
            }
            if ($variantImage->getSize() > 2 * 1024 * 1024) {
                $validator->errors()->add('variants_json', __('labels.variant_image_max_size', ['variant' => $variant['title']]));

                return true;
            }
        }
        if (empty($variant['attributes'])) {
            $validator->errors()->add('variants_json', __('labels.attributes_required'));

            return true;
        }

        if (empty($variant['title'])) {
            $validator->errors()->add('variants_json', __('labels.variant_title_required'));

            return true;
        }

        if (empty($variant['barcode'])) {
            $validator->errors()->add('variants_json', $variant['title'].' '.__('labels.variant_barcode_required'));

            return true;
        }

        if (! in_array($variant['id'], $variantIds)) {
            $validator->errors()->add(
                'pricing',
                __('labels.pricing_information_missing_for_variant', ['variant' => $variant['title']])
            );
        }

        return false;
    }

    /**
     * Validate that variant barcodes are unique.
     */
    protected function validateVariantBarcodeUniqueness($validator, array $variants): void
    {
        $barcodes = [];
        $duplicateBarcodes = [];

        foreach ($variants as $variant) {
            if (! empty($variant['barcode'])) {
                $barcode = $variant['barcode'];
                if (in_array($barcode, $barcodes)) {
                    if (! in_array($barcode, $duplicateBarcodes)) {
                        $duplicateBarcodes[] = $barcode;
                    }
                } else {
                    $barcodes[] = $barcode;
                }
            }
        }

        if (! empty($duplicateBarcodes)) {
            $validator->errors()->add(
                'variants_json',
                __('labels.variant_barcodes_must_be_unique', ['barcodes' => implode(', ', $duplicateBarcodes)])
            );
        }
    }

    /**
     * Validate simple product pricing.
     */
    protected function validateSimpleProduct($validator, array $pricingArr): void
    {
        if (! isset($pricingArr['store_pricing']) || ! is_array($pricingArr['store_pricing'])) {
            $validator->errors()->add('pricing', __('labels.store_pricing_array'));

            return;
        }

        foreach ($pricingArr['store_pricing'] as $index => $row) {
            $this->validatePricingRow($validator, $row, "store_pricing.$index");
        }
    }

    /**
     * Validate a single pricing row.
     */
    protected function validatePricingRow($validator, array $row, string $prefix): void
    {
        $this->validatePricingFields($validator, $row, $prefix);
        $this->validatePricingStoreOwnership($validator, $row, $prefix);
        $this->validateSpecialPriceLogic($validator, $row, $prefix);
    }

    /**
     * Validate individual pricing fields.
     */
    protected function validatePricingFields($validator, array $row, string $prefix): void
    {
        $fieldValidations = [
            'store_id' => [
                'condition' => ! isset($row['store_id']) || ! is_numeric($row['store_id']),
                'message' => __('validation.store_id_required'),
            ],
            'price' => [
                'condition' => ! isset($row['price']) || ! is_numeric($row['price']),
                'message' => __('labels.price_required_numeric'),
            ],
            'special_price' => [
                'condition' => isset($row['special_price']) && $row['special_price'] !== '' &&
                    (! is_numeric($row['special_price']) || $row['special_price'] < 0),
                'message' => __('labels.special_price_numeric_non_negative'),
            ],
            'cost' => [
                'condition' => ! isset($row['cost']) || ! is_numeric($row['cost']),
                'message' => __('labels.cost_required_numeric'),
            ],
            'stock' => [
                'condition' => ! isset($row['stock']) || ! is_numeric($row['stock']) || intval($row['stock']) < 0,
                'message' => __('labels.stock_required_non_negative'),
            ],
            'sku' => [
                'condition' => ! isset($row['sku']) || ! $row['sku'],
                'message' => __('labels.sku_required'),
            ],
        ];

        foreach ($fieldValidations as $field => $validation) {
            if ($validation['condition']) {
                $validator->errors()->add("pricing.$prefix.$field", $validation['message']);
            }
        }
    }

    /**
     * Ensure each pricing row points to a store owned by the authenticated seller.
     */
    protected function validatePricingStoreOwnership($validator, array $row, string $prefix): void
    {
        if (! $this->isSellerRequest()) {
            return;
        }

        if (! isset($row['store_id']) || ! is_numeric($row['store_id'])) {
            return;
        }

        if (! in_array((int) $row['store_id'], $this->getSellerStoreIds(), true)) {
            $validator->errors()->add("pricing.$prefix.store_id", __('labels.invalid_store_for_seller'));
        }
    }

    /**
     * Validate special price logic in relation to regular price.
     */
    protected function validateSpecialPriceLogic($validator, array $row, string $prefix): void
    {
        $hasValidPrice = isset($row['price']) && is_numeric($row['price']);
        $hasValidSpecialPrice = isset($row['special_price']) && is_numeric($row['special_price']) && $row['special_price'] !== '';

        if ($hasValidPrice && $hasValidSpecialPrice && $row['special_price'] >= $row['price']) {
            $validator->errors()->add(
                "pricing.$prefix.special_price",
                __('labels.special_price_less_than_price')
            );
        }

    }

    /**
     * Determine whether the current request needs seller-store-pricing scoping.
     * True for sellers, and for admins acting on behalf of a seller via the picker.
     */
    protected function isSellerRequest(): bool
    {
        return $this->resolveSellerIdForScope() !== null;
    }

    /**
     * True when the request is coming from the admin panel.
     */
    protected function isAdminPanelRequest(): bool
    {
        return $this->is('admin/*') || $this->routeIs('admin.*');
    }

    /**
     * Resolve which seller this request is acting on:
     *   - admin: seller_id submitted via the in-form picker
     *   - seller: authenticated seller
     */
    protected function resolveSellerIdForScope(): ?int
    {
        if ($this->isAdminPanelRequest()) {
            $submitted = $this->input('seller_id');
            return is_numeric($submitted) ? (int) $submitted : null;
        }

        $sellerId = $this->user()?->seller()?->id;
        return $sellerId ? (int) $sellerId : null;
    }

    /**
     * Get the target seller's store IDs once per request.
     */
    protected function getSellerStoreIds(): array
    {
        if ($this->sellerStoreIds !== null) {
            return $this->sellerStoreIds;
        }

        $sellerId = $this->resolveSellerIdForScope();
        if (! $sellerId) {
            return $this->sellerStoreIds = [];
        }

        return $this->sellerStoreIds = Store::query()
            ->where('seller_id', $sellerId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
