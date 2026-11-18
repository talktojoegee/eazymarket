<?php

namespace App\Services\BulkUpload;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Interfaces\BulkImportable;
use App\Interfaces\GroupedBulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingProfile;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\TaxClass;
use App\Services\AttributeService;
use App\Services\ProductService;
use App\Services\SpatieMediaService;
use App\Services\SubscriptionUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductBulkImportService implements GroupedBulkImportable
{
    private array $rowWarnings = [];

    private bool $previewOnly = false;

    private ?array $previewIdentifierOwners = null;

    private array $previewReferenceCache = [];

    private array $previewSubscriptionAllowed = [];

    private ?bool $previewSubscriptionEnabled = null;

    private array $previewInformation = [];

    private array $previewAttributes = [];

    private array $previewSwatchCache = [];

    public function __construct(
        private readonly ProductService $productService,
        private readonly SubscriptionUsageService $subscriptionUsage,
        private readonly AttributeService $attributeService,
    ) {}

    public function moduleKey(): string
    {
        return 'products';
    }

    public function modelClass(): string
    {
        return Product::class;
    }

    public function csvTemplate(): array
    {
        return $this->template('simple');
    }

    public function template(string $name): array
    {
        $columns = $this->exportColumns();
        $simple = [
            '', 'classic-tshirt', 'Classic T-shirt', '', 'Clothing', '', 'Acme', 'simple', 'active', 'Everyday cotton t-shirt',
            'Comfortable cotton t-shirt for everyday wear.', '', 'contain', 'https://example.com/product.jpg',
            'https://example.com/back.jpg|https://example.com/detail.jpg', '1', '1', '5', 'yes', '7', 'yes',
            'preparing', 'yes', 'no', 'required', 'no', 'no', '', '', '6 months', '', 'India', '6109',
            'cotton|tshirt', '', '', 'no', '', '', 'Classic cotton t-shirt', 'cotton|tshirt', 'Comfortable cotton t-shirt.',
            '', 'CLASSIC-TSHIRT', 'BAR-001', '', '', '', '', '', '', '999', '899', '600', '0.25', '2', '30', '25',
            'yes', 'yes', '', '', '', 'Main Warehouse', 'yes', 'yes', '100',
        ];
        $simple = array_pad($simple, count($columns), '');
        $variant = $simple;
        $variant[array_search('handle', $columns, true)] = 'premium-polo';
        $variant[array_search('title', $columns, true)] = 'Premium Polo';
        $variant[array_search('type', $columns, true)] = 'variant';
        $variant[array_search('variant_sku', $columns, true)] = 'POLO-RED-M';
        $variant[array_search('option1_name', $columns, true)] = 'Color';
        $variant[array_search('option1_value', $columns, true)] = 'Red';
        $variant[array_search('option2_name', $columns, true)] = 'Size';
        $variant[array_search('option2_value', $columns, true)] = 'M';
        $variant[array_search('variant_image_url', $columns, true)] = 'https://example.com/polo-red.jpg';
        $variantSecond = array_fill(0, count($columns), '');
        $variantSecond[array_search('handle', $columns, true)] = 'premium-polo';
        $variantSecond[array_search('variant_sku', $columns, true)] = 'POLO-BLUE-L';
        $variantSecond[array_search('barcode', $columns, true)] = 'BAR-POLO-BLUE-L';
        $variantSecond[array_search('option1_name', $columns, true)] = 'Color';
        $variantSecond[array_search('option1_value', $columns, true)] = 'Blue';
        $variantSecond[array_search('option2_name', $columns, true)] = 'Size';
        $variantSecond[array_search('option2_value', $columns, true)] = 'L';
        $variantSecond[array_search('price', $columns, true)] = '1099';
        $variantSecond[array_search('special_price', $columns, true)] = '999';
        $variantSecond[array_search('cost', $columns, true)] = '700';
        $variantSecond[array_search('is_default', $columns, true)] = 'no';
        $variantSecond[array_search('store_location_name', $columns, true)] = 'Main Warehouse';
        $variantSecond[array_search('sell_from', $columns, true)] = 'yes';
        $variantSecond[array_search('tracked', $columns, true)] = 'yes';
        $variantSecond[array_search('quantity', $columns, true)] = '50';

        return [
            'columns' => $columns,
            'sample' => $name === 'variant' ? $variant : $simple,
            'samples' => $name === 'variant' ? [$variant, $variantSecond] : [$simple],
        ];
    }

    public function availableFields(): array
    {
        $aliases = [
            'main_image_url' => ['image_src', 'main_image', 'image_url'],
            'additional_image_urls' => ['additional_images', 'additional_image_urls'],
            'variant_sku' => ['sku'],
            'price' => ['variant_price'],
            'special_price' => ['variant_special_price'],
            'cost' => ['variant_cost'],
            'quantity' => ['variant_stock', 'stock', 'qty'],
            'barcode' => ['variant_barcode'],
            'weight' => ['variant_weight'],
            'height' => ['variant_height'],
            'length' => ['variant_length'],
            'breadth' => ['variant_breadth'],
            'is_default' => ['variant_is_default'],
            'country_of_manufacture' => ['made_in'],
            'video_url' => ['video_link'],
        ];

        return array_map(fn (string $key) => [
            'key' => $key,
            'label' => 'labels.bulk_upload_product_field_'.$key,
            'required' => in_array($key, ['handle', 'title', 'type'], true),
            'type' => str_ends_with($key, '_url') ? 'url' : 'string',
            'aliases' => $aliases[$key] ?? [],
        ], $this->exportColumns());
    }

    public function validateMapping(array $mapping, array $headers): void
    {
        foreach (['handle', 'title', 'type'] as $required) {
            if (empty($mapping[$required])) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_mapping_required', ['field' => $required]));
            }
        }
        if (empty($mapping['category_id']) && empty($mapping['category_title'])) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_product_category_mapping_required'));
        }
        foreach ($mapping as $header) {
            if ($header !== null && $header !== '' && ! in_array($header, $headers, true)) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_unknown_csv_column'));
            }
        }
    }

    public function suggestedMapping(array $headers): array
    {
        $normalizedHeaders = array_map([$this, 'normalizeHeader'], $headers);
        $mapping = [];
        foreach ($this->availableFields() as $field) {
            $candidates = array_map([$this, 'normalizeHeader'], array_merge([$field['key'], __($field['label'])], $field['aliases'] ?? []));
            foreach ($normalizedHeaders as $index => $header) {
                if (in_array($header, $candidates, true)) {
                    $mapping[$field['key']] = $headers[$index];
                    break;
                }
            }
        }

        return $mapping;
    }

    public function previewGroup(array $rows, array $mapping, int $sellerId, string $operation, ?string $matchField): array
    {
        $job = new BulkUploadJob([
            'seller_id' => $sellerId,
            'operation' => $operation,
            'match_field' => $matchField,
            'column_mapping' => $mapping,
        ]);
        $this->rowWarnings = [];
        $this->previewInformation = [];
        $this->previewAttributes = [];
        $this->previewOnly = true;
        $critical = [];
        $warnings = [];
        $information = [];

        try {
            $mapped = array_map(fn (array $row) => $this->mappedRow($row, $job), $rows);
            $this->validateProductLevelConsistency($mapped);
            $first = $mapped[0] ?? array_fill_keys($this->exportColumns(), '');
            $product = $operation === 'update' ? $this->findProduct($first, $sellerId, $job) : null;
            if ($operation === 'update' && ! $product) {
                $warnings[] = __('labels.bulk_upload_product_no_match');
            } elseif ($operation === 'update' && ! $this->hasUpdateValues($mapped, (string) $matchField)) {
                $warnings[] = __('labels.bulk_upload_product_unchanged');
            } else {
                $payload = $this->buildPayload($mapped, $sellerId, $product);
                unset($payload['image_src']);
                $validator = Validator::make($payload, $this->payloadRules($operation, $product?->id));
                if ($validator->fails()) {
                    $critical[] = $validator->errors()->has('handle') && Product::query()->where('slug', $payload['handle'])->exists()
                        ? __('labels.bulk_upload_product_existing_handle_create')
                        : $validator->errors()->first();
                } else {
                    $this->validateVariantBusinessRules($payload, $product?->id);
                }
                if ($operation === 'create' && ($this->previewSubscriptionEnabled ??= Setting::isSubscriptionEnabled()) && ! ($this->previewSubscriptionAllowed[$sellerId]
                    ??= $this->subscriptionUsage->canUse($sellerId, SubscriptionPlanKeyEnum::PRODUCT_LIMIT()))) {
                    $critical[] = __('labels.subscription_limit_reached');
                }
            }
        } catch (\Throwable $exception) {
            $mapped ??= [];
            $first ??= $mapped[0] ?? array_fill_keys($this->exportColumns(), '');
            $critical[] = $exception->getMessage();
        } finally {
            $this->previewOnly = false;
        }

        $warnings = array_merge($warnings, $this->rowWarnings);
        $information = array_merge($information, $this->previewInformation);
        $mainImage = $this->firstMappedValue($mapped, 'main_image_url');
        foreach (array_filter(array_merge(
            $this->list($this->firstMappedValue($mapped, 'additional_image_urls')),
            array_column($mapped, 'variant_image_url'),
        )) as $url) {
            if (! $this->isPublicHttpsUrl((string) $url)) {
                $warnings[] = __('labels.bulk_upload_product_preview_optional_media_warning', ['url' => $url]);
            }
        }
        if ($mainImage !== '' && ! $this->isPublicHttpsUrl($mainImage)) {
            $warnings[] = __('labels.bulk_upload_product_preview_main_media_warning');
        }

        $variantGroups = [];
        foreach ($mapped as $row) {
            $key = $row['variant_id'] ?: ($row['variant_sku'] ?: implode('|', [$row['option1_value'], $row['option2_value'], $row['option3_value']]));
            $variantGroups[$key ?: 'default'][] = $row;
        }
        $resolvedVariants = [];
        if (isset($payload)) {
            $resolvedVariants = $payload['type'] === 'variant'
                ? (json_decode((string) ($payload['variants_json'] ?? '[]'), true) ?: [])
                : [[
                    'id' => $this->firstMappedValue($mapped, 'variant_id'),
                    'sku' => $payload['sku'] ?? $this->firstMappedValue($mapped, 'variant_sku'),
                    'inventory' => $payload['inventory'] ?? [],
                ]];
        }
        $variants = [];
        $locationKeys = [];
        $prices = [];
        $inventoryTotal = 0;
        foreach ($variantGroups as $variantRows) {
            $variant = $variantRows[0];
            $locations = [];
            $resolvedVariant = collect($resolvedVariants)->first(function (array $candidate) use ($variant) {
                if ($variant['variant_id'] !== '' && (string) ($candidate['id'] ?? '') === $variant['variant_id']) {
                    return true;
                }

                return $variant['variant_sku'] !== '' && strcasecmp((string) ($candidate['sku'] ?? ''), $variant['variant_sku']) === 0;
            });
            if ($resolvedVariant) {
                foreach ($resolvedVariant['inventory'] ?? [] as $inventory) {
                    $locationId = (string) ($inventory['store_location_id'] ?? '');
                    if ($locationId === '') {
                        continue;
                    }
                    $locationKeys[$locationId] = true;
                    $locations[] = [
                        'store_id' => '',
                        'id' => $locationId,
                        'name' => '',
                        'sell_from' => $inventory['sell_from'] ?? false,
                        'tracked' => $inventory['tracked'] ?? false,
                        'quantity' => $inventory['qty'] ?? 0,
                    ];
                    if (is_numeric($inventory['qty'] ?? null)) {
                        $inventoryTotal += (int) $inventory['qty'];
                    }
                }
            } else {
                foreach ($variantRows as $row) {
                    $locationKey = $row['store_location_id'] ?: ($row['store_location_name'] ?: $row['store_id']);
                    if ($locationKey === '') {
                        continue;
                    }
                    $locationKeys[strtolower((string) $locationKey)] = true;
                    $locations[] = [
                        'store_id' => $row['store_id'],
                        'id' => $row['store_location_id'],
                        'name' => $row['store_location_name'],
                        'sell_from' => $row['sell_from'],
                        'tracked' => $row['tracked'],
                        'quantity' => $row['quantity'],
                    ];
                    if (is_numeric($row['quantity'])) {
                        $inventoryTotal += (int) $row['quantity'];
                    }
                }
            }
            if (is_numeric($variant['price'])) {
                $prices[] = (float) $variant['price'];
            }
            $variants[] = [
                'id' => $variant['variant_id'],
                'sku' => $variant['variant_sku'],
                'barcode' => $variant['barcode'],
                'options' => array_values(array_filter(array_map(fn (int $index) => $this->previewOption(
                    $variant,
                    $index,
                    $resolvedVariant['attributes'][$index - 1] ?? null,
                ), [1, 2, 3]))),
                'price' => $variant['price'],
                'special_price' => $variant['special_price'],
                'cost' => $variant['cost'],
                'available' => $variant['availability'],
                'is_default' => $variant['is_default'],
                'image_url' => $variant['variant_image_url'],
                'locations' => $locations,
            ];
        }

        $critical = array_values(array_unique(array_filter($critical)));
        $warnings = array_values(array_unique(array_filter($warnings)));

        return [
            'product' => [
                'id' => $this->firstMappedValue($mapped, 'product_id'),
                'handle' => $this->firstMappedValue($mapped, 'handle'),
                'title' => $this->firstMappedValue($mapped, 'title'),
                'type' => $this->firstMappedValue($mapped, 'type'),
                'status' => $this->firstMappedValue($mapped, 'status'),
                'category' => $this->firstMappedValue($mapped, 'category_title') ?: $this->firstMappedValue($mapped, 'category_id'),
                'brand' => $this->firstMappedValue($mapped, 'brand_title') ?: $this->firstMappedValue($mapped, 'brand_id'),
                'short_description' => $this->firstMappedValue($mapped, 'short_description'),
                'description' => $this->firstMappedValue($mapped, 'description'),
                'main_image_url' => $mainImage,
                'additional_image_urls' => $this->list($this->firstMappedValue($mapped, 'additional_image_urls')),
                'shipping_profile' => $this->firstMappedValue($mapped, 'shipping_profile_name') ?: $this->firstMappedValue($mapped, 'shipping_profile_id'),
                'tax_classes' => $this->firstMappedValue($mapped, 'tax_class_names') ?: $this->firstMappedValue($mapped, 'tax_class_ids'),
                'seo_title' => $this->firstMappedValue($mapped, 'seo_title'),
                'seo_description' => $this->firstMappedValue($mapped, 'seo_description'),
            ],
            'variants' => $variants,
            'variant_count' => count($variants),
            'location_count' => count($locationKeys),
            'location_keys' => array_keys($locationKeys),
            'minimum_price' => $prices === [] ? null : min($prices),
            'maximum_price' => $prices === [] ? null : max($prices),
            'inventory_total' => $locationKeys === [] ? null : $inventoryTotal,
            'issues' => ['critical' => $critical, 'warning' => $warnings, 'info' => $information],
            'attributes' => array_values(array_map(function (array $attribute) {
                $attribute['values'] = array_values($attribute['values']);

                return $attribute;
            }, $this->previewAttributes)),
        ];
    }

    public function primePreviewVariantIdentifiers(array $skus, array $barcodes): void
    {
        $this->previewIdentifierOwners = [];
        foreach (array_chunk(array_values(array_unique(array_filter(array_map('strtolower', $skus)))), 400) as $chunk) {
            ProductVariant::query()->whereIn(DB::raw('LOWER(sku)'), $chunk)->get(['product_id', 'sku'])->each(function ($variant) {
                $this->previewIdentifierOwners['sku:'.strtolower((string) $variant->sku)] = (int) $variant->product_id;
            });
        }
        foreach (array_chunk(array_values(array_unique(array_filter(array_map('strtolower', $barcodes)))), 400) as $chunk) {
            ProductVariant::query()->whereIn(DB::raw('LOWER(barcode)'), $chunk)->get(['product_id', 'barcode'])->each(function ($variant) {
                $this->previewIdentifierOwners['barcode:'.strtolower((string) $variant->barcode)] = (int) $variant->product_id;
            });
        }
    }

    public function groupingKey(array $row, BulkUploadJob $job): string
    {
        return strtolower($this->value($row, $job, 'handle'));
    }

    public function processRow(array $row, BulkUploadJob $job): array
    {
        return $this->processGroup([$row], $job);
    }

    public function processGroup(array $rows, BulkUploadJob $job): array
    {
        try {
            return DB::transaction(function () use ($rows, $job) {
                $this->rowWarnings = [];
                $sellerId = (int) $job->seller_id;
                if ($sellerId < 1) {
                    throw new \InvalidArgumentException(__('labels.bulk_upload_product_seller_required'));
                }

                $mapped = array_map(fn (array $row) => $this->mappedRow($row, $job), $rows);
                $this->validateProductLevelConsistency($mapped);
                $first = $mapped[0];
                $operation = $this->enumValue($job->operation);
                $product = $operation === 'update' ? $this->findProduct($first, $sellerId, $job) : null;
                if ($operation === 'update' && ! $product) {
                    return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_product_no_match')];
                }
                if ($operation === 'update' && ! $this->hasUpdateValues($mapped, (string) $job->match_field)) {
                    return [BulkImportable::OUTCOME_SKIPPED, __('labels.bulk_upload_product_unchanged')];
                }

                $payload = $this->buildPayload($mapped, $sellerId, $product);
                $mainImageUrl = $payload['image_src'] ?? null;
                unset($payload['image_src']);
                $validator = Validator::make($payload, $this->payloadRules($operation, $product?->id));
                if ($validator->fails()) {
                    if (isset($validator->failed()['handle']['Unique'])) {
                        return [BulkImportable::OUTCOME_FAILED, __('labels.bulk_upload_product_duplicate_handle_log', ['handle' => $payload['handle']])];
                    }

                    return [BulkImportable::OUTCOME_FAILED, $validator->errors()->first()];
                }
                $this->validateVariantBusinessRules($payload, $product?->id);

                if ($operation === 'create' && Setting::isSubscriptionEnabled() && ! $this->subscriptionUsage->canUse($sellerId, SubscriptionPlanKeyEnum::PRODUCT_LIMIT())) {
                    return [BulkImportable::OUTCOME_FAILED, __('labels.subscription_limit_reached')];
                }

                $request = Request::create('/', 'POST', $payload);
                $request->setUserResolver(fn () => $job->user);
                $request->attributes->set('handled_by_admin', $job->panel === 'admin');
                $result = $product
                    ? $this->productService->updateProduct($product, $payload, $request)
                    : $this->productService->storeProduct($payload, $request);
                $saved = $result['product'];
                $this->syncShippingProfile($saved, $payload['shipping_profile_id'] ?? null);
                $notices = array_values(array_unique($this->rowWarnings));
                $mediaWarnings = array_values(array_unique($this->attachOptionalMedia($saved, $mapped, $mainImageUrl)));

                if ($operation === 'create' && Setting::isSubscriptionEnabled()) {
                    $usage = $this->subscriptionUsage->recordUsageOrFail($sellerId, SubscriptionPlanKeyEnum::PRODUCT_LIMIT());
                    if (! ($usage['success'] ?? false)) {
                        throw new \RuntimeException(__('labels.subscription_limit_reached'));
                    }
                }

                $message = __($operation === 'create' ? 'labels.bulk_upload_product_created_log' : 'labels.bulk_upload_product_updated_log', ['title' => $saved->title]);
                if ($notices !== []) {
                    $message .= ' '.__('labels.bulk_upload_product_notes_log', ['notes' => implode(' ', $notices)]);
                }
                if ($mediaWarnings !== []) {
                    $message .= ' '.__('labels.bulk_upload_product_images_log', ['warnings' => implode('; ', $mediaWarnings)]);
                }

                return [$operation === 'create' ? BulkImportable::OUTCOME_CREATED : BulkImportable::OUTCOME_UPDATED, $message];
            }, 3);
        } catch (\Throwable $exception) {
            return [BulkImportable::OUTCOME_FAILED, $exception->getMessage()];
        }
    }

    public function previewSummary(array $headers, array $rows): array
    {
        $normalized = array_map([$this, 'normalizeHeader'], $headers);
        $handleIndex = array_search('handle', $normalized, true);
        $skuIndex = array_search('variant_sku', $normalized, true);
        $locationIndex = array_search('store_location_id', $normalized, true);
        $products = [];
        $variants = [];
        $locations = [];
        foreach ($rows as $row) {
            $handle = $handleIndex === false ? '' : trim((string) ($row[$handleIndex] ?? ''));
            $sku = $skuIndex === false ? '' : trim((string) ($row[$skuIndex] ?? ''));
            $location = $locationIndex === false ? '' : trim((string) ($row[$locationIndex] ?? ''));
            if ($handle !== '') {
                $products[strtolower($handle)] = true;
            }
            if ($sku !== '') {
                $variants[strtolower($handle).'|'.strtolower($sku)] = true;
            }
            if ($location !== '') {
                $locations[strtolower($handle).'|'.strtolower($sku).'|'.$location] = true;
            }
        }

        return [
            'product_count' => count($products),
            'variant_count' => count($variants),
            'location_count' => count($locations),
        ];
    }

    public function exportColumns(): array
    {
        return [
            'product_id', 'handle', 'title', 'category_id', 'category_title', 'brand_id', 'brand_title', 'type', 'status',
            'short_description', 'description', 'indicator', 'image_fit', 'main_image_url', 'additional_image_urls',
            'minimum_order_quantity', 'quantity_step_size', 'total_allowed_quantity', 'is_returnable',
            'returnable_days', 'is_cancelable', 'cancelable_till', 'allow_cod', 'is_attachment_required', 'attachment_mode',
            'featured', 'requires_otp', 'video_type', 'video_url', 'warranty', 'guarantee', 'country_of_manufacture',
            'hsn_code', 'tags', 'tax_class_ids', 'tax_class_names', 'inclusive_tax', 'shipping_profile_id',
            'shipping_profile_name', 'seo_title', 'seo_keywords', 'seo_description', 'variant_id', 'variant_sku', 'barcode',
            'option1_name', 'option1_value', 'option2_name', 'option2_value', 'option3_name', 'option3_value', 'price',
            'special_price', 'cost', 'weight', 'height', 'length', 'breadth', 'availability', 'is_default',
            'variant_image_url', 'store_id', 'store_location_id', 'store_location_name', 'sell_from', 'tracked', 'quantity',
        ];
    }

    public function exportRow($model): array
    {
        $model->loadMissing(['category', 'brand', 'taxClasses', 'shippingProfile', 'variants.attributes.attribute', 'variants.attributes.attributeValue', 'variants.locationInventories.storeLocation']);
        $variant = $model->variants->first();
        $inventory = $variant?->locationInventories->first();
        $attributes = $variant?->attributes ?? collect();
        $metadata = is_array($model->metadata) ? $model->metadata : [];

        return [
            'product_id' => $model->id,
            'handle' => $model->slug,
            'title' => $model->title,
            'category_id' => $model->category_id,
            'category_title' => $model->category?->title,
            'brand_id' => $model->brand_id,
            'brand_title' => $model->brand?->title,
            'type' => $model->type,
            'status' => $this->enumValue($model->status),
            'short_description' => $model->short_description,
            'description' => $model->description,
            'indicator' => $model->indicator,
            'image_fit' => $model->image_fit,
            'main_image_url' => $model->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE()),
            'additional_image_urls' => $model->getMedia(SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE())->pluck('original_url')->implode('|'),
            'minimum_order_quantity' => $model->minimum_order_quantity,
            'quantity_step_size' => $model->quantity_step_size,
            'total_allowed_quantity' => $model->total_allowed_quantity,
            'is_returnable' => $model->is_returnable,
            'returnable_days' => $model->returnable_days,
            'is_cancelable' => $model->is_cancelable,
            'cancelable_till' => $model->cancelable_till,
            'allow_cod' => $model->allow_cod,
            'is_attachment_required' => $model->is_attachment_required,
            'attachment_mode' => $model->attachment_mode,
            'featured' => $model->featured,
            'requires_otp' => $model->requires_otp,
            'video_type' => $model->video_type,
            'video_url' => $model->video_link,
            'warranty' => $model->warranty_period,
            'guarantee' => $model->guarantee_period,
            'country_of_manufacture' => $model->made_in,
            'hsn_code' => $model->hsn_code,
            'tags' => implode('|', is_array($model->tags) ? $model->tags : (json_decode((string) $model->tags, true) ?: [])),
            'tax_class_ids' => $model->taxClasses->pluck('id')->implode('|'),
            'tax_class_names' => $model->taxClasses->pluck('title')->implode('|'),
            'inclusive_tax' => $model->is_inclusive_tax,
            'shipping_profile_id' => $model->shippingProfile->first()?->id,
            'shipping_profile_name' => $model->shippingProfile->first()?->name,
            'seo_title' => $metadata['seo_title'] ?? '',
            'seo_keywords' => implode('|', (array) ($metadata['seo_keywords'] ?? [])),
            'seo_description' => $metadata['seo_description'] ?? '',
            'variant_id' => $variant?->id,
            'variant_sku' => $variant?->sku,
            'barcode' => $variant?->barcode,
            'option1_name' => $attributes->get(0)?->attribute?->title,
            'option1_value' => $attributes->get(0)?->attributeValue?->title,
            'option2_name' => $attributes->get(1)?->attribute?->title,
            'option2_value' => $attributes->get(1)?->attributeValue?->title,
            'option3_name' => $attributes->get(2)?->attribute?->title,
            'option3_value' => $attributes->get(2)?->attributeValue?->title,
            'price' => $variant?->price_exclude_tax,
            'special_price' => $variant?->special_price_exclude_tax,
            'cost' => $variant?->cost,
            'weight' => $variant?->weight,
            'height' => $variant?->height,
            'length' => $variant?->length,
            'breadth' => $variant?->breadth,
            'availability' => $variant?->availability ? 'yes' : 'no',
            'is_default' => $variant?->is_default ? 'yes' : 'no',
            'variant_image_url' => $variant?->getFirstMediaUrl(SpatieMediaCollectionName::VARIANT_IMAGE()),
            'store_id' => $inventory?->storeLocation?->store_id,
            'store_location_id' => $inventory?->store_location_id,
            'store_location_name' => $inventory?->storeLocation?->name,
            'sell_from' => $inventory?->is_fulfillment_location ? 'yes' : 'no',
            'tracked' => $inventory?->tracked ? 'yes' : 'no',
            'quantity' => $inventory?->stock,
        ];
    }

    public function exportRows(Product $product): array
    {
        $product->loadMissing(['category', 'brand', 'taxClasses', 'shippingProfile', 'variants.attributes.attribute', 'variants.attributes.attributeValue', 'variants.locationInventories.storeLocation']);
        if ($product->variants->isEmpty()) {
            return [$this->exportRow($product)];
        }

        $rows = [];
        foreach ($product->variants as $variant) {
            $inventories = $variant->locationInventories->isEmpty() ? collect([null]) : $variant->locationInventories;
            foreach ($inventories as $inventory) {
                $productCopy = clone $product;
                $variantCopy = clone $variant;
                $variantCopy->setRelation('locationInventories', $inventory ? collect([$inventory]) : collect());
                $productCopy->setRelation('variants', collect([$variantCopy]));
                $rows[] = $this->exportRow($productCopy);
            }
        }

        return $rows;
    }

    public function exportFilters(): array
    {
        return [];
    }

    public function applyExportFilters($query, array $filters)
    {
        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', (int) $filters['seller_id']);
        }

        return $query;
    }

    public function availableMatchFields(): array
    {
        return [
            ['key' => 'product_id', 'label' => 'labels.product_id', 'column' => 'product_id'],
            ['key' => 'handle', 'label' => 'labels.handle', 'column' => 'handle'],
        ];
    }

    private function buildPayload(array $rows, int $sellerId, ?Product $product): array
    {
        $first = $rows[0];
        $type = strtolower($first['type'] ?: ($product?->type ?? ''));
        if ($product && $first['type'] !== '' && $type !== $product->type) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_product_type_change_unsupported'));
        }
        $categoryId = $product && $first['category_id'] === '' && $first['category_title'] === ''
            ? $product->category_id
            : $this->resolveNamedReference(Category::query(), $first['category_id'], $first['category_title'], 'category');
        $brandId = $this->resolveNamedReference(Brand::query(), $first['brand_id'], $first['brand_title'], 'brand', true);

        $payload = [
            'seller_id' => $sellerId,
            'category_id' => $categoryId ?: $product?->category_id,
            'brand_id' => $brandId ?: $product?->brand_id,
            'title' => $first['title'] ?: $product?->title,
            'handle' => $first['handle'] ?: $product?->slug,
            'type' => $type,
            'status' => $first['status'] ?: ($this->enumValue($product?->status) ?: 'active'),
            'short_description' => $first['short_description'] ?: $product?->short_description,
            'description' => $first['description'] ?: $product?->description,
            'indicator' => $first['indicator'] ?: $product?->indicator,
            'image_fit' => $first['image_fit'] ?: ($product?->image_fit ?? 'contain'),
            'base_prep_time' => $product?->base_prep_time ?? 0,
            'minimum_order_quantity' => $this->numberOrCurrent($first['minimum_order_quantity'], $product?->minimum_order_quantity, 1),
            'quantity_step_size' => $this->numberOrCurrent($first['quantity_step_size'], $product?->quantity_step_size, 1),
            'total_allowed_quantity' => $this->numberOrCurrent($first['total_allowed_quantity'], $product?->total_allowed_quantity, 0),
            'is_returnable' => $this->booleanOrCurrent($first['is_returnable'], $product?->is_returnable, false),
            'returnable_days' => $this->numberOrCurrent($first['returnable_days'], $product?->returnable_days, null),
            'is_cancelable' => $this->booleanOrCurrent($first['is_cancelable'], $product?->is_cancelable, false),
            'cancelable_till' => $first['cancelable_till'] ?: $product?->cancelable_till,
            'allow_cod' => $this->booleanOrCurrent($first['allow_cod'], $product?->allow_cod, true),
            'is_attachment_required' => $this->booleanOrCurrent($first['is_attachment_required'], $product?->is_attachment_required, false),
            'attachment_mode' => $first['attachment_mode'] ?: ($product?->attachment_mode ?? 'required'),
            'featured' => $this->booleanOrCurrent($first['featured'], $product?->featured, false),
            'requires_otp' => $this->booleanOrCurrent($first['requires_otp'], $product?->requires_otp, false),
            'video_type' => $first['video_type'] ?: $product?->video_type,
            'video_link' => $first['video_url'] ?: $product?->video_link,
            'warranty_period' => $first['warranty'] ?: $product?->warranty_period,
            'guarantee_period' => $first['guarantee'] ?: $product?->guarantee_period,
            'made_in' => $first['country_of_manufacture'] ?: $product?->made_in,
            'hsn_code' => $first['hsn_code'] ?: $product?->hsn_code,
            'tags' => $first['tags'] !== '' ? $this->list($first['tags']) : $this->currentTags($product),
            'tax_groups' => $this->resolveTaxClasses($first, $sellerId, $product),
            'is_inclusive_tax' => $this->booleanOrCurrent($first['inclusive_tax'], $product?->is_inclusive_tax, false),
            'metadata' => $this->metadata($first, $product),
            'pricing' => '{}',
            'shipping_profile_id' => $this->resolveShippingProfile($first, $sellerId, $product),
        ];
        if ($first['main_image_url'] !== '') {
            $payload['image_src'] = $first['main_image_url'];
        }

        if ($type === 'simple') {
            $variant = $product?->variants()->with('locationInventories')->first();
            $payload = array_merge($payload, $this->simpleVariantPayload($rows, $sellerId, $variant));
        } else {
            $payload['variants_json'] = json_encode($this->variantPayloads($rows, $sellerId, $product), JSON_THROW_ON_ERROR);
        }

        return $payload;
    }

    private function simpleVariantPayload(array $rows, int $sellerId, ?ProductVariant $variant): array
    {
        $first = $rows[0];

        return [
            'price' => $first['price'] !== '' ? $first['price'] : ($variant?->getRawOriginal('price') ?? null),
            'special_price' => $first['special_price'] !== '' ? $first['special_price'] : ($variant?->getRawOriginal('special_price') ?? null),
            'cost' => $first['cost'] !== '' ? $first['cost'] : ($variant?->getRawOriginal('cost') ?? null),
            'sku' => $first['variant_sku'] ?: $variant?->sku,
            'barcode' => $first['barcode'] ?: $variant?->barcode,
            'weight' => $first['weight'] !== '' ? $first['weight'] : $variant?->weight,
            'height' => $first['height'] !== '' ? $first['height'] : $variant?->height,
            'length' => $first['length'] !== '' ? $first['length'] : $variant?->length,
            'breadth' => $first['breadth'] !== '' ? $first['breadth'] : $variant?->breadth,
            'inventory' => $this->inventoryPayload($rows, $sellerId, $variant),
        ];
    }

    private function variantPayloads(array $rows, int $sellerId, ?Product $product): array
    {
        $existing = $product?->variants()->with(['attributes', 'locationInventories'])->get() ?? collect();
        $payloads = $existing->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $this->existingVariantPayload($variant)])->all();
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['variant_id'] ?: strtolower($row['variant_sku']);
            if ($key === '') {
                $key = strtolower(implode('|', [$row['option1_value'], $row['option2_value'], $row['option3_value']]));
            }
            $groups[$key][] = $row;
        }
        foreach ($groups as $groupRows) {
            $first = $groupRows[0];
            $matched = null;
            if ($first['variant_id'] !== '') {
                $matched = $existing->firstWhere('id', (int) $first['variant_id']);
            } elseif ($first['variant_sku'] !== '') {
                $matched = $existing->first(fn (ProductVariant $variant) => strcasecmp((string) $variant->sku, $first['variant_sku']) === 0);
            }
            $base = $matched ? $this->existingVariantPayload($matched) : [];
            $attributes = $this->optionAttributes($first, $sellerId);
            if ($attributes === [] && $matched) {
                $attributes = $base['attributes'];
            }
            if ($attributes === []) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_variant_options_required'));
            }
            $id = $matched?->id ?? 'new-'.count($payloads);
            $payloads[$id] = array_merge($base, [
                'id' => $id,
                'title' => implode(' / ', array_filter([$first['option1_value'], $first['option2_value'], $first['option3_value']])) ?: ($matched?->title ?? 'Variant'),
                'attributes' => $attributes,
                'sku' => $first['variant_sku'] ?: $matched?->sku,
                'barcode' => $first['barcode'] ?: $matched?->barcode,
                'price' => $first['price'] !== '' ? $first['price'] : ($matched?->getRawOriginal('price') ?? null),
                'special_price' => $first['special_price'] !== '' ? $first['special_price'] : ($matched?->getRawOriginal('special_price') ?? null),
                'cost' => $first['cost'] !== '' ? $first['cost'] : ($matched?->getRawOriginal('cost') ?? null),
                'weight' => $first['weight'] !== '' ? $first['weight'] : ($matched?->weight ?? 1),
                'height' => $first['height'] !== '' ? $first['height'] : ($matched?->height ?? 1),
                'length' => $first['length'] !== '' ? $first['length'] : ($matched?->length ?? 1),
                'breadth' => $first['breadth'] !== '' ? $first['breadth'] : ($matched?->breadth ?? 1),
                'availability' => $first['availability'] !== '' ? ($this->boolean($first['availability']) ? 'yes' : 'no') : (($matched?->availability ?? true) ? 'yes' : 'no'),
                'is_default' => $first['is_default'] !== '' ? ($this->boolean($first['is_default']) ? 'on' : 'off') : (($matched?->is_default ?? false) ? 'on' : 'off'),
                'inventory' => $this->inventoryPayload($groupRows, $sellerId, $matched),
            ]);
        }

        return array_values($payloads);
    }

    private function existingVariantPayload(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'title' => $variant->title,
            'attributes' => $variant->attributes->map(fn ($attribute) => [
                'attribute_id' => $attribute->global_attribute_id,
                'value_id' => $attribute->global_attribute_value_id,
            ])->values()->all(),
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->getRawOriginal('price'),
            'special_price' => $variant->getRawOriginal('special_price'),
            'cost' => $variant->getRawOriginal('cost'),
            'weight' => $variant->weight,
            'height' => $variant->height,
            'length' => $variant->length,
            'breadth' => $variant->breadth,
            'availability' => $variant->availability ? 'yes' : 'no',
            'is_default' => $variant->is_default ? 'on' : 'off',
            'inventory' => $variant->locationInventories->map(fn ($inventory) => [
                'store_location_id' => $inventory->store_location_id,
                'sell_from' => $inventory->is_fulfillment_location,
                'tracked' => $inventory->tracked,
                'qty' => $inventory->stock,
            ])->values()->all(),
        ];
    }

    private function inventoryPayload(array $rows, int $sellerId, ?ProductVariant $variant): array
    {
        $inventory = $variant?->locationInventories?->keyBy('store_location_id')->map(fn ($item) => [
            'store_location_id' => $item->store_location_id,
            'sell_from' => $item->is_fulfillment_location,
            'tracked' => $item->tracked,
            'qty' => $item->stock,
        ])->all() ?? [];
        $hasLocationInput = collect($rows)->contains(fn (array $row) => $row['store_location_id'] !== ''
            || $row['store_location_name'] !== ''
            || $row['store_id'] !== '');
        if (! $hasLocationInput && $inventory !== []) {
            return array_values($inventory);
        }
        foreach ($rows as $row) {
            $locationId = $this->resolveLocation($row, $sellerId);
            if (! $locationId) {
                continue;
            }
            $inventory[$locationId] = [
                'store_location_id' => $locationId,
                'sell_from' => $row['sell_from'] === '' ? true : $this->boolean($row['sell_from']),
                'tracked' => $row['tracked'] === '' ? true : $this->boolean($row['tracked']),
                'qty' => $row['quantity'] === '' ? 0 : (int) $row['quantity'],
            ];
        }

        return array_values($inventory);
    }

    private function optionAttributes(array $row, int $sellerId): array
    {
        $attributes = [];
        for ($index = 1; $index <= 3; $index++) {
            $name = $row['option'.$index.'_name'];
            $value = $row['option'.$index.'_value'];
            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '' || $value === '') {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_option_pair_required', ['index' => $index]));
            }
            $matches = GlobalProductAttribute::query()->where('seller_id', $sellerId)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($name)])->limit(2)->get();
            if ($matches->count() > 1) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_option_name_ambiguous', ['name' => $name]));
            }
            $attribute = $matches->first();
            if (! $attribute && $this->previewOnly) {
                $this->trackPreviewOption($name, $value, null, null);
                $this->previewInformation[] = __('labels.bulk_upload_product_preview_attribute_will_be_created', [
                    'attribute' => $name,
                    'value' => $value,
                ]);
                $attributes[] = ['attribute_id' => crc32('attribute:'.strtolower($name)) + 1000000000, 'value_id' => crc32('value:'.strtolower($name).':'.strtolower($value)) + 1000000000];

                continue;
            }
            $valueMatches = $attribute ? $attribute->values()->whereRaw('LOWER(title) = ?', [mb_strtolower($value)])->limit(2)->get() : collect();
            if ($valueMatches->count() > 1) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_option_value_ambiguous', ['attribute' => $name, 'value' => $value]));
            }
            $attributeValue = $valueMatches->first();
            if (! $attributeValue && $this->previewOnly) {
                if ($attribute && $attribute->swatche_type !== 'text') {
                    throw new \InvalidArgumentException(__('labels.bulk_upload_attribute_configure_swatch_first'));
                }
                $this->trackPreviewOption($name, $value, $attribute, null);
                $this->previewInformation[] = __('labels.bulk_upload_product_preview_value_will_be_created', [
                    'attribute' => $name,
                    'value' => $value,
                ]);
                $attributes[] = ['attribute_id' => $attribute?->id ?? crc32('attribute:'.mb_strtolower($name)) + 1000000000, 'value_id' => crc32('value:'.($attribute?->id ?? $name).':'.mb_strtolower($value)) + 1000000000];

                continue;
            }
            if ($this->previewOnly) {
                $this->trackPreviewOption($name, $value, $attribute, $attributeValue);
            }
            $attributes[] = $this->attributeService->resolveVariantOption($sellerId, $name, $value);
        }

        return $attributes;
    }

    private function trackPreviewOption(string $name, string $value, ?GlobalProductAttribute $attribute, ?GlobalProductAttributeValue $attributeValue): void
    {
        $key = mb_strtolower($name);
        $this->previewAttributes[$key] ??= [
            'name' => $name,
            'id' => $attribute?->id,
            'status' => $attribute ? 'existing' : 'new',
            'swatch_type' => $attribute?->swatche_type ?? 'text',
            'values' => [],
        ];
        $valueKey = mb_strtolower($value);
        $this->previewAttributes[$key]['values'][$valueKey] = [
            'name' => $value,
            'id' => $attributeValue?->id,
            'status' => $attributeValue ? 'existing' : 'new',
        ];
    }

    private function previewOption(array $row, int $index, ?array $resolved): ?array
    {
        $name = $row['option'.$index.'_name'];
        $value = $row['option'.$index.'_value'];
        $valueId = $resolved['value_id'] ?? null;
        if ($name === '' && $value === '' && ! $valueId) {
            return null;
        }
        $swatch = null;
        if ($resolved && $valueId && ctype_digit((string) $valueId)) {
            if (! array_key_exists((int) $valueId, $this->previewSwatchCache)) {
                $this->previewSwatchCache[(int) $valueId] = GlobalProductAttributeValue::query()
                    ->with('attribute:id,title,swatche_type')
                    ->find((int) $valueId);
            }
            $swatch = $this->previewSwatchCache[(int) $valueId];
        }

        return [
            'name' => $name ?: ($swatch?->attribute?->title ?? ''),
            'value' => $value ?: ($swatch?->title ?? ''),
            'swatch_type' => $swatch?->swatch_type,
            'swatch_color' => $swatch?->swatch_color,
            'swatch_image_url' => $swatch?->swatch_image_url,
        ];
    }

    private function firstMappedValue(array $rows, string $field): string
    {
        foreach ($rows as $row) {
            if (($row[$field] ?? '') !== '') {
                return (string) $row[$field];
            }
        }

        return '';
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host !== ''
            && $host !== 'localhost'
            && (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                || ! filter_var($host, FILTER_VALIDATE_IP));
    }

    private function resolveLocation(array $row, int $sellerId): ?int
    {
        $cacheKey = 'location:'.$sellerId.':'.implode(':', [$row['store_location_id'], strtolower($row['store_location_name']), $row['store_id']]);
        if ($this->previewOnly && array_key_exists($cacheKey, $this->previewReferenceCache)) {
            return $this->previewReferenceCache[$cacheKey];
        }
        $query = StoreLocation::query()->whereHas('store', fn ($builder) => $builder->where('seller_id', $sellerId));
        if ($row['store_location_id'] !== '') {
            $location = (clone $query)->find((int) $row['store_location_id']);
            if (! $location) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_invalid_location'));
            }
            if ($row['store_location_name'] !== '' && strcasecmp($location->name, $row['store_location_name']) !== 0) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_reference_mismatch', ['field' => 'location']));
            }

            return $this->cachePreviewReference($cacheKey, $location->id);
        }
        if ($row['store_location_name'] !== '') {
            return $this->cachePreviewReference($cacheKey, $this->uniqueName($query, 'name', $row['store_location_name'], 'location'));
        }
        if ($row['store_id'] !== '') {
            $store = Store::query()->where('seller_id', $sellerId)->find((int) $row['store_id']);
            $location = $store?->locations()->where('is_default', true)->first();
            if (! $location) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_legacy_store_without_location'));
            }
            $this->rowWarnings[] = __('labels.bulk_upload_product_legacy_store_resolved', [
                'store' => $store->id,
                'location' => $location->id,
            ]);

            return $this->cachePreviewReference($cacheKey, $location->id);
        }

        $store = Store::query()->where('seller_id', $sellerId)->orderBy('id')->first();
        $location = $store?->locations()->where('is_default', true)->first();
        if (! $store || ! $location) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_product_default_location_unavailable'));
        }
        $this->rowWarnings[] = __('labels.bulk_upload_product_default_location_resolved', [
            'store' => $store->id,
            'location' => $location->id,
        ]);

        return $this->cachePreviewReference($cacheKey, $location->id);
    }

    private function resolveNamedReference($query, string $id, string $name, string $field, bool $nullable = false): ?int
    {
        $cacheKey = 'reference:'.$field.':'.$id.':'.strtolower($name);
        if ($this->previewOnly && array_key_exists($cacheKey, $this->previewReferenceCache)) {
            return $this->previewReferenceCache[$cacheKey];
        }
        if ($id !== '') {
            $record = (clone $query)->find((int) $id);
            if (! $record) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_invalid_reference', ['field' => $field]));
            }
            $label = $record->title ?? $record->name;
            if ($name !== '' && strcasecmp((string) $label, $name) !== 0) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_reference_mismatch', ['field' => $field]));
            }

            return $this->cachePreviewReference($cacheKey, $record->id);
        }
        if ($name !== '') {
            return $this->cachePreviewReference($cacheKey, $this->uniqueName($query, in_array($field, ['location', 'shipping profile'], true) ? 'name' : 'title', $name, $field));
        }
        if ($nullable) {
            return null;
        }

        throw new \InvalidArgumentException(__('labels.bulk_upload_product_invalid_reference', ['field' => $field]));
    }

    private function cachePreviewReference(string $key, ?int $value): ?int
    {
        if ($this->previewOnly) {
            $this->previewReferenceCache[$key] = $value;
        }

        return $value;
    }

    private function uniqueName($query, string $column, string $name, string $field): int
    {
        $matches = (clone $query)->whereRaw('LOWER('.$column.') = ?', [strtolower($name)])->limit(2)->get();
        if ($matches->count() !== 1) {
            throw new \InvalidArgumentException($matches->isEmpty()
                ? __('labels.bulk_upload_product_invalid_reference', ['field' => $field])
                : __('labels.bulk_upload_product_ambiguous_reference', ['field' => $field]));
        }

        return (int) $matches->first()->id;
    }

    private function resolveTaxClasses(array $row, int $sellerId, ?Product $product): array
    {
        $ids = $this->list($row['tax_class_ids']);
        $names = $this->list($row['tax_class_names']);
        if ($ids === [] && $names === []) {
            return $product?->taxClasses()->pluck('tax_classes.id')->all() ?? [];
        }
        $allowed = TaxClass::query()->whereHas('market.stores', fn ($query) => $query->where('stores.seller_id', $sellerId));
        $resolvedIds = array_map('intval', $ids);
        $resolvedNames = [];
        foreach ($names as $name) {
            $resolvedNames[] = $this->uniqueName($allowed, 'title', $name, 'tax class');
        }
        if ($resolvedIds !== [] && $resolvedNames !== []) {
            $idSet = array_values(array_unique($resolvedIds));
            $nameSet = array_values(array_unique($resolvedNames));
            sort($idSet);
            sort($nameSet);
            if ($idSet !== $nameSet) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_reference_mismatch', ['field' => 'tax class']));
            }
        }
        $resolved = array_values(array_unique($resolvedIds ?: $resolvedNames));
        $valid = (clone $allowed)->whereIn('id', $resolved)->count();
        if ($valid !== count($resolved)) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_product_invalid_reference', ['field' => 'tax class']));
        }

        return $resolved;
    }

    private function resolveShippingProfile(array $row, int $sellerId, ?Product $product): ?int
    {
        if ($row['shipping_profile_id'] === '' && $row['shipping_profile_name'] === '') {
            return $product?->shippingProfile()->value('shipping_profiles.id');
        }
        $query = ShippingProfile::query()->whereHas('store', fn ($builder) => $builder->where('seller_id', $sellerId));

        return $this->resolveNamedReference($query, $row['shipping_profile_id'], $row['shipping_profile_name'], 'shipping profile', true);
    }

    private function syncShippingProfile(Product $product, ?int $profileId): void
    {
        if ($profileId) {
            DB::table('shipping_profile_products')->where('product_id', $product->id)->delete();
            DB::table('shipping_profile_products')->insert([
                'shipping_profile_id' => $profileId,
                'product_id' => $product->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function attachOptionalMedia(Product $product, array $rows, ?string $updateMainImageUrl): array
    {
        $warnings = [];
        $currentMainUrl = $product->getFirstMedia(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE())?->original_url;
        if ($updateMainImageUrl && $updateMainImageUrl !== $currentMainUrl) {
            try {
                SpatieMediaService::uploadFromUrl($product, $updateMainImageUrl, SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
            } catch (\Throwable $exception) {
                $warnings[] = __('labels.bulk_upload_product_optional_image_failed', ['url' => $updateMainImageUrl, 'reason' => $exception->getMessage()]);
            }
        }
        $existingAdditionalUrls = $product->getMedia(SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE())
            ->pluck('original_url')
            ->filter()
            ->all();
        foreach ($this->list($rows[0]['additional_image_urls']) as $url) {
            if (in_array($url, $existingAdditionalUrls, true)) {
                continue;
            }
            try {
                SpatieMediaService::uploadFromUrl($product, $url, SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());
            } catch (\Throwable $exception) {
                $warnings[] = __('labels.bulk_upload_product_optional_image_failed', ['url' => $url, 'reason' => $exception->getMessage()]);
            }
        }
        foreach ($rows as $row) {
            if ($row['variant_image_url'] === '') {
                continue;
            }
            $variant = $row['variant_id'] !== ''
                ? $product->variants()->find((int) $row['variant_id'])
                : $product->variants()->where('sku', $row['variant_sku'])->first();
            if (! $variant) {
                $warnings[] = __('labels.bulk_upload_product_variant_image_unmatched', ['sku' => $row['variant_sku']]);

                continue;
            }
            if ($variant->getFirstMedia(SpatieMediaCollectionName::VARIANT_IMAGE())?->original_url === $row['variant_image_url']) {
                continue;
            }
            try {
                SpatieMediaService::uploadFromUrl($variant, $row['variant_image_url'], SpatieMediaCollectionName::VARIANT_IMAGE());
            } catch (\Throwable $exception) {
                $warnings[] = __('labels.bulk_upload_product_optional_image_failed', ['url' => $row['variant_image_url'], 'reason' => $exception->getMessage()]);
            }
        }

        return $warnings;
    }

    private function findProduct(array $row, int $sellerId, BulkUploadJob $job): ?Product
    {
        $query = Product::query()->where('seller_id', $sellerId);
        $matchField = $job->match_field ?: 'product_id';
        $value = $row[$matchField] ?? '';
        if ($value === '') {
            throw new \InvalidArgumentException(__('labels.bulk_upload_row_missing_match_value', ['field' => $matchField]));
        }

        return $matchField === 'handle'
            ? $query->where('slug', $value)->first()
            : $query->find((int) $value);
    }

    private function validateProductLevelConsistency(array $rows): void
    {
        $fields = ['product_id', 'handle', 'title', 'category_id', 'category_title', 'brand_id', 'brand_title', 'type', 'status'];
        foreach ($fields as $field) {
            $values = array_values(array_unique(array_filter(array_column($rows, $field), fn ($value) => $value !== '')));
            if (count($values) > 1) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_conflicting_group_value', ['field' => $field]));
            }
        }
    }

    private function mappedRow(array $row, BulkUploadJob $job): array
    {
        $mapped = [];
        foreach ($this->exportColumns() as $field) {
            $mapped[$field] = $this->value($row, $job, $field);
        }

        return $mapped;
    }

    private function value(array $row, BulkUploadJob $job, string $field): string
    {
        $mapping = is_array($job->column_mapping) ? $job->column_mapping : [];
        $header = $mapping[$field] ?? null;
        $value = $header ? ($row[$header] ?? null) : null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function hasUpdateValues(array $rows, string $matchField): bool
    {
        $identityFields = array_filter(['product_id', 'handle', $matchField]);

        foreach ($rows as $row) {
            foreach ($row as $field => $value) {
                if (! in_array($field, $identityFields, true) && $value !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    private function payloadRules(string $operation, ?int $productId): array
    {
        return [
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($productId)],
            'type' => ['required', 'in:simple,variant'],
            'status' => ['required', 'in:active,draft'],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'image_fit' => ['required', 'in:cover,contain'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'quantity_step_size' => ['required', 'integer', 'min:1'],
            'total_allowed_quantity' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0.01'],
            'special_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'variants_json' => ['required_if:type,variant', 'nullable', 'json'],
            'hsn_code' => ['nullable', 'regex:/^\d{4}(\d{2}(\d{2})?)?$/'],
        ];
    }

    private function validateVariantBusinessRules(array $payload, ?int $productId): void
    {
        $variants = $payload['type'] === 'variant'
            ? (json_decode((string) ($payload['variants_json'] ?? '[]'), true) ?: [])
            : [[
                'sku' => $payload['sku'] ?? null,
                'barcode' => $payload['barcode'] ?? null,
                'price' => $payload['price'] ?? null,
                'special_price' => $payload['special_price'] ?? null,
                'cost' => $payload['cost'] ?? null,
                'inventory' => $payload['inventory'] ?? [],
                'is_default' => 'on',
                'attributes' => [],
            ]];
        if ($variants === []) {
            throw new \InvalidArgumentException(__('labels.variant_required'));
        }

        $skus = [];
        $barcodes = [];
        $combinations = [];
        $dimensions = null;
        $hasDefault = false;
        foreach ($variants as $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));
            $barcode = trim((string) ($variant['barcode'] ?? ''));
            $price = $variant['price'] ?? null;
            $specialPrice = $variant['special_price'] ?? null;
            if ($sku === '') {
                throw new \InvalidArgumentException(__('labels.sku_required'));
            }
            if ($barcode === '') {
                throw new \InvalidArgumentException(__('labels.variant_barcode_required'));
            }
            if (! is_numeric($price) || (float) $price <= 0) {
                throw new \InvalidArgumentException(__('labels.price_required'));
            }
            if ($specialPrice !== null && $specialPrice !== '' && (! is_numeric($specialPrice) || (float) $specialPrice > (float) $price)) {
                throw new \InvalidArgumentException(__('labels.special_price_should_be_less_than_price'));
            }
            if (isset($skus[strtolower($sku)]) || isset($barcodes[strtolower($barcode)])) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_duplicate_variant_identifier'));
            }
            $skus[strtolower($sku)] = true;
            $barcodes[strtolower($barcode)] = true;

            $attributes = collect($variant['attributes'] ?? [])->sortBy('attribute_id')->values();
            $dimensionIds = $attributes->pluck('attribute_id')->map(fn ($id) => (int) $id)->all();
            if ($payload['type'] === 'variant') {
                if ($dimensions === null) {
                    $dimensions = $dimensionIds;
                } elseif ($dimensions !== $dimensionIds) {
                    throw new \InvalidArgumentException(__('labels.variant_dimension_mismatch'));
                }
                $combination = $attributes->map(fn ($item) => $item['attribute_id'].':'.$item['value_id'])->implode('|');
                if (isset($combinations[$combination])) {
                    throw new \InvalidArgumentException(__('labels.duplicate_variant_combination'));
                }
                $combinations[$combination] = true;
            }
            $hasDefault = $hasDefault || in_array($variant['is_default'] ?? null, ['on', true, 1, '1'], true);
            $inventory = collect($variant['inventory'] ?? []);
            if (! $inventory->contains(fn ($row) => ! empty($row['sell_from']) || ! empty($row['is_fulfillment_location']))) {
                throw new \InvalidArgumentException(__('labels.bulk_upload_product_fulfillment_location_required'));
            }
            foreach ($inventory as $row) {
                if (! empty($row['tracked']) && (! isset($row['qty']) || ! is_numeric($row['qty']) || (int) $row['qty'] < 0)) {
                    throw new \InvalidArgumentException(__('labels.bulk_upload_product_tracked_quantity_required'));
                }
            }
        }
        if ($payload['type'] === 'variant' && ! $hasDefault) {
            throw new \InvalidArgumentException(__('labels.default_variant_required'));
        }

        if ($this->previewOnly && $this->previewIdentifierOwners !== null) {
            $conflict = collect(array_keys($skus))->contains(fn ($sku) => isset($this->previewIdentifierOwners['sku:'.$sku]) && $this->previewIdentifierOwners['sku:'.$sku] !== (int) $productId)
                || collect(array_keys($barcodes))->contains(fn ($barcode) => isset($this->previewIdentifierOwners['barcode:'.$barcode]) && $this->previewIdentifierOwners['barcode:'.$barcode] !== (int) $productId);
        } else {
            $conflict = ProductVariant::query()
                ->whereHas('product', fn ($query) => $productId ? $query->whereKeyNot($productId) : $query)
                ->where(fn ($query) => $query->whereIn(DB::raw('LOWER(sku)'), array_keys($skus))->orWhereIn(DB::raw('LOWER(barcode)'), array_keys($barcodes)))
                ->exists();
        }
        if ($conflict) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_product_variant_identifier_exists'));
        }
    }

    private function metadata(array $row, ?Product $product): array
    {
        $metadata = is_array($product?->metadata) ? $product->metadata : [];
        foreach (['seo_title', 'seo_description'] as $field) {
            if ($row[$field] !== '') {
                $metadata[$field] = $row[$field];
            }
        }
        if ($row['seo_keywords'] !== '') {
            $metadata['seo_keywords'] = $this->list($row['seo_keywords']);
        }

        return $metadata;
    }

    private function currentTags(?Product $product): array
    {
        if (! $product) {
            return [];
        }
        if (is_array($product->tags)) {
            return $product->tags;
        }

        return json_decode((string) $product->tags, true) ?: [];
    }

    private function list(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[|,]/', $value) ?: []), fn ($item) => $item !== ''));
    }

    private function numberOrCurrent(string $value, mixed $current, mixed $default): mixed
    {
        return $value !== '' ? $value : ($current ?? $default);
    }

    private function booleanOrCurrent(string $value, mixed $current, bool $default): bool
    {
        return $value !== '' ? $this->boolean($value) : (is_null($current) ? $default : (bool) $current);
    }

    private function boolean(string $value): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new \InvalidArgumentException(__('labels.bulk_upload_product_invalid_boolean', ['value' => $value]));
        }

        return $parsed;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $header), '_'));
    }

    private function enumValue(mixed $value): string
    {
        return is_object($value) && property_exists($value, 'value') ? (string) $value->value : (string) $value;
    }
}
