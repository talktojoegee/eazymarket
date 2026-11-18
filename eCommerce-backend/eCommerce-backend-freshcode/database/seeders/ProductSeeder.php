<?php

namespace Database\Seeders;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StoreLocationInventory;
use App\Models\StoreProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $jsonPath = database_path('seeders/Data/products_data.json');
            if (!file_exists($jsonPath)) {
                $this->command?->error("JSON file not found at: $jsonPath");
                return;
            }

            $productsData = json_decode(file_get_contents($jsonPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->command?->error("JSON parse error: " . json_last_error_msg());
                return;
            }

            foreach ($productsData as $productData) {
                // Skip if not an array or missing required fields
                if (!is_array($productData) || !isset($productData['seller_email'], $productData['category_title'], $productData['title'], $productData['type'])) {
                    $this->command?->warn("Skipping product entry: missing required fields");
                    continue;
                }

                // Find seller by email
                $seller = Seller::whereHas('user', fn($q) => $q->where('email', $productData['seller_email']))->first();
                if (!$seller) {
                    $this->command?->warn("Seller with email '{$productData['seller_email']}' not found. Skipping product '{$productData['title']}'.");
                    continue;
                }

                // Find seller's store (first store associated)
                $store = Store::where('seller_id', $seller->id)->first();
                if (!$store) {
                    $this->command?->warn("No store found for seller '{$productData['seller_email']}'. Skipping product '{$productData['title']}'.");
                    continue;
                }

                // Ensure default location for this store
                $location = $this->ensureDefaultLocation($store);

                // Find category
                $category = Category::where('title', $productData['category_title'])->first();
                if (!$category) {
                    $this->command?->warn("Category '{$productData['category_title']}' not found. Skipping product '{$productData['title']}'.");
                    continue;
                }

                // Find brand
                $brandId = null;
                if (!empty($productData['brand_title'])) {
                    $brand = Brand::where('title', $productData['brand_title'])->first();
                    if ($brand) {
                        $brandId = $brand->id;
                    } else {
                        $this->command?->warn("Brand '{$productData['brand_title']}' not found for product '{$productData['title']}'.");
                    }
                }

                if ($productData['type'] === 'simple') {
                    $this->createSimpleProduct($seller, $category, $productData, $store, $location, $brandId);
                } elseif ($productData['type'] === 'variant') {
                    $this->createVariantProduct($seller, $category, $productData, $store, $location, $brandId);
                }
            }
        });

        $this->command?->info('Products seeded successfully.');
    }

    private function createSimpleProduct(Seller $seller, Category $category, array $data, Store $store, StoreLocation $location, ?int $brandId = null): void
    {
        $tags = $data['tags'] ?? [];
        $product = $this->createBaseProduct($seller, $category, $data['title'], ProductTypeEnum::SIMPLE(), $brandId, $tags);
        if (!$product) {
            return; // already exists
        }

        $variant = $this->makeVariant($product, [
            'title' => $product->title,
            'price' => $data['price'],
            'special_price' => $data['special_price'],
            'sku' => $data['sku'],
            'is_default' => true,
        ]);
        $this->stockVariant($variant, $store, $location, $data['stock']);
    }

    private function createVariantProduct(Seller $seller, Category $category, array $data, Store $store, StoreLocation $location, ?int $brandId = null): void
    {
        $tags = $data['tags'] ?? [];
        $product = $this->createBaseProduct($seller, $category, $data['title'], ProductTypeEnum::VARIANT(), $brandId, $tags);
        if (!$product) {
            return;
        }

        // Create or retrieve attributes and their values (store in a map)
        $attributeMap = []; // attribute_name => ['id' => id, 'values' => [value_title => value_id]]
        foreach ($data['attributes'] as $attrDef) {
            $attr = $this->ensureAttribute($seller->id, $attrDef['name'], 'text');
            $valueMap = [];
            foreach ($attrDef['values'] as $valueTitle) {
                $value = $this->ensureAttributeValue($attr->id, $valueTitle);
                $valueMap[$valueTitle] = $value->id;
            }
            $attributeMap[$attrDef['name']] = [
                'id' => $attr->id,
                'values' => $valueMap,
            ];
        }

        // Create variants
        foreach ($data['variants'] as $index => $variantData) {
            // Build variant title with all attribute values separated by " / "
            $attrParts = [];
            foreach ($variantData['attribute_values'] as $attrName => $attrValue) {
                $attrParts[] = $attrValue;
            }
            $variantTitle = $product->title . ' - ' . implode(' / ', $attrParts);

            $variant = $this->makeVariant($product, [
                'title' => $variantTitle,
                'price' => $variantData['price'],
                'special_price' => $variantData['special_price'],
                'sku' => $variantData['sku'],
                'is_default' => $index === 0,
            ]);

            // Attach each attribute value to this variant
            foreach ($variantData['attribute_values'] as $attrName => $attrValue) {
                if (!isset($attributeMap[$attrName])) {
                    $this->command?->warn("Attribute '{$attrName}' not defined in attributes list for product '{$data['title']}'. Skipping.");
                    continue;
                }
                $attrId = $attributeMap[$attrName]['id'];
                $valueId = $attributeMap[$attrName]['values'][$attrValue] ?? null;
                if ($valueId) {
                    $this->attachAttribute($product->id, $variant->id, $attrId, $valueId);
                } else {
                    $this->command?->warn("Value '{$attrValue}' for attribute '{$attrName}' not found. Skipping.");
                }
            }

            $this->stockVariant($variant, $store, $location, $variantData['stock']);
        }
    }
    // -------------------------------------------------------------------------
    // Reuse your existing helper methods (createBaseProduct, makeVariant, etc.)
    // They are exactly the same as in your original seeder.
    // -------------------------------------------------------------------------

    private function createBaseProduct(Seller $seller, Category $category, string $title, string $type, ?int $brandId = null, array $tags = []): ?Product
    {
        if (Product::where('seller_id', $seller->id)->where('title', $title)->exists()) {
            return null;
        }

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brandId,  // <-- new field
            'title' => $title,
            'type' => $type,
            'short_description' => Str::limit($title . ' — a quality demo product.', 180, ''),
            'description' => '<p>' . $title . ' seeded for local development and QA.</p>',
            'base_prep_time' => 10,
            'minimum_order_quantity' => 1,
            'quantity_step_size' => 1,
            'total_allowed_quantity' => 100,
            'is_inclusive_tax' => '0',
            'is_returnable' => '1',
            'returnable_days' => 7,
            'is_cancelable' => '0',
            'status' => ProductStatusEnum::ACTIVE(),
            'verification_status' => ProductVarificationStatusEnum::APPROVED(),
            'tags' => !empty($tags) ? json_encode($tags) : json_encode(['demo', 'seed']),
            'image_fit' => 'cover',
        ]);
    }

    private function makeVariant(Product $product, array $data): ProductVariant
    {
        return ProductVariant::create([
            'uuid' => (string)Str::uuid(),
            'product_id' => $product->id,
            'title' => $data['title'],
            'weight' => '1',
            'height' => '1',
            'breadth' => '1',
            'length' => '1',
            'availability' => 1,
            'barcode' => rand(1000000000, 9999999999),
            'sku' => $data['sku'] ?? null,
            'price' => $data['price'],
            'special_price' => $data['special_price'] ?? $data['price'],
            'cost' => $data['cost'] ?? null,
            'visibility' => 'published',
            'is_default' => (bool)($data['is_default'] ?? false),
        ]);
    }

    private function stockVariant(ProductVariant $variant, Store $store, StoreLocation $location, int $stock): void
    {
        StoreLocationInventory::updateOrCreate(
            [
                'store_location_id' => $location->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'is_fulfillment_location' => true,
                'tracked' => true,
                'stock' => $stock,
                'low_stock_threshold' => 5,
                'reserved' => 0,
                'last_restocked_at' => now(),
            ]
        );

        StoreProductVariant::firstOrCreate([
            'product_variant_id' => $variant->id,
            'store_id' => $store->id,
        ]);
    }

    private function attachAttribute(int $productId, int $variantId, int $attrId, int $valueId): void
    {
        ProductVariantAttribute::firstOrCreate([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'global_attribute_id' => $attrId,
            'global_attribute_value_id' => $valueId,
        ]);
    }

    private function ensureAttribute(int $sellerId, string $title, string $swatcheType = 'text'): GlobalProductAttribute
    {
        $slug = Str::slug($title);

        // Check if slug exists for this seller
        $existing = GlobalProductAttribute::where('seller_id', $sellerId)
            ->where('title', $title)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Make slug unique globally
        $originalSlug = $slug;
        $counter = 1;
        while (GlobalProductAttribute::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return GlobalProductAttribute::create([
            'seller_id' => $sellerId,
            'title' => $title,
            'label' => $title,
            'slug' => $slug,
            'swatche_type' => $swatcheType,
        ]);
    }

    private function ensureAttributeValue(int $attributeId, string $title, ?string $swatcheValue = null): GlobalProductAttributeValue
    {
        return GlobalProductAttributeValue::firstOrCreate(
            ['global_attribute_id' => $attributeId, 'title' => $title],
            ['swatche_value' => $swatcheValue]
        );
    }

    private function ensureDefaultLocation(Store $store): StoreLocation
    {
        $existing = StoreLocation::where('store_id', $store->id)
            ->where('is_default', true)
            ->first();
        if ($existing) {
            return $existing;
        }

        return StoreLocation::create([
            'store_id' => $store->id,
            'name' => 'Main Location',
            'address_1' => $store->address ?? '221B Market Street',
            'city' => $store->city ?? 'San Francisco',
            'state' => $store->state ?? 'California',
            'country_iso2' => 'US',
            'country_name' => $store->country ?? 'United States',
            'pin_code' => $store->zipcode ?? '94102',
            'latitude' => $store->latitude ?? 37.774929,
            'longitude' => $store->longitude ?? -122.419416,
            'is_physical_storefront' => true,
            'fulfills_online_orders' => true,
            'is_active' => true,
            'is_default' => true,
            'status' => 'active',
        ]);
    }
}
