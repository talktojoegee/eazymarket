<?php

namespace App\Services;

use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\ComboPack;
use App\Models\ComboPackItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StoreLocationInventory;
use App\Models\StoreProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComboPackService
{
    public function syncProductKit(Product $product, int $storeId, array $componentRows, bool $admin): ComboPack
    {
        return DB::transaction(function () use ($product, $storeId, $componentRows, $admin) {
            $store = Store::query()->whereKey($storeId)->where('seller_id', $product->seller_id)->first();
            if (! $store || (! $admin && ! in_array($storeId, app(\App\Services\SellerStoreService::class)->accessibleStoreIds(), true))) {
                throw ValidationException::withMessages(['kit_store_id' => __('labels.combo_pack_store_seller_mismatch')]);
            }

            $components = collect($componentRows)->map(function (array $row) use ($product, $store) {
                $variant = ProductVariant::query()
                    ->with(['product.taxClasses', 'product.shippingProfile'])
                    ->whereKey((int) $row['product_variant_id'])
                    ->whereHas('product', fn ($query) => $query
                        ->where('seller_id', $product->seller_id)
                        ->where('status', 'active')
                        ->where('verification_status', ProductVarificationStatusEnum::APPROVED()))
                    ->whereHas('storeProductVariants', fn ($query) => $query->where('store_id', $store->id))
                    ->first();

                if (! $variant || $variant->product?->comboPack) {
                    throw ValidationException::withMessages(['kit_components' => __('labels.combo_pack_component_unavailable')]);
                }

                return ['variant' => $variant, 'quantity' => (int) $row['quantity']];
            });

            if ($components->isEmpty()) {
                throw ValidationException::withMessages(['kit_components' => __('labels.combo_pack_components_required')]);
            }

            $firstProduct = $components->first()['variant']->product;
            $profileIds = $firstProduct->shippingProfile->modelKeys();
            $profileIds = array_values(array_map('intval', $profileIds));
            sort($profileIds);
            foreach ($components as $component) {
                $componentProfileIds = array_values(array_map('intval', $component['variant']->product->shippingProfile->modelKeys()));
                sort($componentProfileIds);
                if ($componentProfileIds !== $profileIds) {
                    throw ValidationException::withMessages(['kit_components' => __('labels.combo_pack_shipping_profile_mismatch')]);
                }
            }

            $parentVariant = $product->variants()->where('is_default', true)->first();
            if (! $parentVariant) {
                throw ValidationException::withMessages(['type' => __('labels.product_variant_required')]);
            }

            $allReturnable = $components->every(fn ($component) => (string) $component['variant']->product->is_returnable === '1');
            $returnableDays = $components->map(fn ($component) => $component['variant']->product->returnable_days)
                ->filter(fn ($days) => $days !== null)->min();

            $product->forceFill([
                'is_inclusive_tax' => (string) $firstProduct->is_inclusive_tax,
                'is_returnable' => $allReturnable ? '1' : '0',
                'returnable_days' => $returnableDays,
                'allow_cod' => $components->every(fn ($component) => (string) ($component['variant']->product->allow_cod ?? '1') === '1') ? '1' : '0',
            ]);
            $metadata = (array) ($product->metadata ?? []);
            $metadata['is_combo_pack'] = true;
            $product->metadata = $metadata;
            $product->save();
            $product->taxClasses()->sync($firstProduct->taxClasses->modelKeys());
            $product->shippingProfile()->sync($profileIds);

            $comboPack = ComboPack::updateOrCreate(
                ['product_id' => $product->id],
                ['store_id' => $store->id],
            );
            $comboPack->items()->delete();
            foreach ($components as $component) {
                ComboPackItem::create([
                    'combo_pack_id' => $comboPack->id,
                    'product_id' => $component['variant']->product_id,
                    'product_variant_id' => $component['variant']->id,
                    'quantity' => $component['quantity'],
                ]);
            }
            $selectedLocationIds = StoreLocationInventory::query()
                ->where('product_variant_id', $parentVariant->id)
                ->where('is_fulfillment_location', true)
                ->whereHas('storeLocation', fn ($query) => $query
                    ->whereHas('store', fn ($storeQuery) => $storeQuery->where('seller_id', $product->seller_id))
                    ->where('is_active', true))
                ->pluck('store_location_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($selectedLocationIds === []) {
                throw ValidationException::withMessages(['inventory' => __('labels.inventory_required')]);
            }

            $locations = StoreLocation::query()
                ->whereHas('store', fn ($query) => $query->where('seller_id', $product->seller_id))
                ->where('is_active', true)
                ->whereIn('id', $selectedLocationIds)
                ->get();
            $selectedStoreIds = $locations->pluck('store_id')->map(fn ($id) => (int) $id)->unique()->values();
            $eligibleStoreIds = app(KitStoreAvailabilityService::class)->eligibleStoreIds(
                $components->pluck('variant.id')->all(),
                (int) $product->seller_id,
                $admin ? [] : app(SellerStoreService::class)->accessibleStoreIds(),
            );
            if ($selectedStoreIds->diff($eligibleStoreIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['inventory' => __('labels.kit_inventory_components_store_mismatch')]);
            }

            StoreProductVariant::query()
                ->where('product_variant_id', $parentVariant->id)
                ->whereNotIn('store_id', $selectedStoreIds->all())
                ->delete();
            foreach ($selectedStoreIds as $selectedStoreId) {
                StoreProductVariant::firstOrCreate([
                    'store_id' => $selectedStoreId,
                    'product_variant_id' => $parentVariant->id,
                ]);
            }

            foreach ($locations as $location) {
                StoreLocationInventory::updateOrCreate(
                    ['store_location_id' => $location->id, 'product_variant_id' => $parentVariant->id],
                    [
                        'is_fulfillment_location' => (bool) $location->fulfills_online_orders,
                        'tracked' => false,
                        'stock' => 0,
                        'reserved' => 0,
                        'low_stock_threshold' => 0,
                    ],
                );
            }
            Cache::forget('combo-pack-product:'.$product->id);

            return $comboPack;
        });
    }

    public function save(array $data, ?ComboPack $comboPack, bool $admin, int $actorSellerId = 0): ComboPack
    {
        return DB::transaction(function () use ($data, $comboPack, $admin, $actorSellerId) {
            $store = Store::query()->with('seller')->findOrFail((int) $data['store_id']);
            $sellerId = $admin ? (int) $store->seller_id : $actorSellerId;

            if ((int) $store->seller_id !== $sellerId) {
                throw ValidationException::withMessages(['store_id' => __('labels.combo_pack_store_seller_mismatch')]);
            }

            $componentIds = collect($data['components'])->pluck('product_variant_id')->map(fn ($id) => (int) $id)->unique();
            if ($componentIds->count() !== count($data['components'])) {
                throw ValidationException::withMessages(['components' => __('labels.combo_pack_duplicate_component')]);
            }

            $components = collect($data['components'])->map(function (array $row) use ($sellerId, $store) {
                $variant = ProductVariant::query()
                    ->with(['product.taxClasses', 'product.shippingProfile'])
                    ->whereKey((int) $row['product_variant_id'])
                    ->whereHas('product', function ($query) use ($sellerId) {
                        $query->where('seller_id', $sellerId)
                            ->where('status', 'active')
                            ->where('verification_status', ProductVarificationStatusEnum::APPROVED());
                    })
                    ->whereHas('storeProductVariants', fn ($query) => $query->where('store_id', $store->id))
                    ->first();

                if (! $variant || $variant->product?->comboPack) {
                    throw ValidationException::withMessages(['components' => __('labels.combo_pack_component_unavailable')]);
                }

                return ['variant' => $variant, 'quantity' => (int) $row['quantity']];
            });

            if ($components->isEmpty()) {
                throw ValidationException::withMessages(['components' => __('labels.combo_pack_components_required')]);
            }

            $firstProduct = $components->first()['variant']->product;
            $profileIds = $firstProduct->shippingProfile->modelKeys();
            $profileIds = array_values(array_map('intval', $profileIds));
            sort($profileIds);
            foreach ($components as $component) {
                $componentProfileIds = array_values(array_map('intval', $component['variant']->product->shippingProfile->modelKeys()));
                sort($componentProfileIds);
                if ($componentProfileIds !== $profileIds) {
                    throw ValidationException::withMessages(['components' => __('labels.combo_pack_shipping_profile_mismatch')]);
                }
            }
            $allReturnable = $components->every(fn ($component) => (string) $component['variant']->product->is_returnable === '1');
            $allCancelable = $components->every(fn ($component) => (string) $component['variant']->product->is_cancelable === '1');
            $returnableDays = $components->map(fn ($component) => $component['variant']->product->returnable_days)
                ->filter(fn ($days) => $days !== null)->min();
            $cancelableTillOrder = ['pending' => 0, 'awaiting_store_response' => 1, 'accepted' => 2, 'preparing' => 3];
            $cancelableTill = $components->pluck('variant.product.cancelable_till')
                ->filter()->sortBy(fn ($value) => $cancelableTillOrder[$value] ?? 99)->first();
            $product = $comboPack?->product ?? new Product();
            $isNew = ! $product->exists;
            $product->forceFill([
                'seller_id' => $sellerId,
                'category_id' => $firstProduct->category_id,
                'title' => trim($data['title']),
                'type' => 'simple',
                'short_description' => trim($data['short_description'] ?? $data['title']),
                'description' => trim($data['description'] ?? ''),
                'minimum_order_quantity' => 1,
                'quantity_step_size' => 1,
                'total_allowed_quantity' => 999999,
                'is_inclusive_tax' => (string) $firstProduct->is_inclusive_tax,
                'is_returnable' => $allReturnable ? '1' : '0',
                'returnable_days' => $returnableDays,
                'is_cancelable' => $allCancelable ? '1' : '0',
                'cancelable_till' => $cancelableTill,
                'is_attachment_required' => '0',
                'base_prep_time' => (int) $firstProduct->base_prep_time,
                'status' => $data['status'] ?? 'active',
                'verification_status' => $isNew
                    ? ($admin ? ProductVarificationStatusEnum::APPROVED() : ProductVarificationStatusEnum::PENDING())
                    : ($admin ? $product->verification_status : ProductVarificationStatusEnum::PENDING()),
                'featured' => '0',
                'allow_cod' => $components->every(fn ($component) => (string) ($component['variant']->product->allow_cod ?? '1') === '1') ? '1' : '0',
                'tags' => '[]',
                'metadata' => ['is_combo_pack' => true],
            ]);
            $product->save();
            $product->taxClasses()->sync($firstProduct->taxClasses->modelKeys());
            $product->shippingProfile()->sync($profileIds);

            $variant = $product->variants()->firstOrNew(['is_default' => true]);
            $variant->forceFill([
                'uuid' => $variant->uuid ?: (string) Str::uuid(),
                'product_id' => $product->id,
                'title' => trim($data['title']),
                'slug' => $product->slug,
                'weight' => $components->sum(fn ($component) => (float) ($component['variant']->weight ?? 0) * $component['quantity']),
                'height' => $components->max(fn ($component) => (float) ($component['variant']->height ?? 0)),
                'breadth' => $components->max(fn ($component) => (float) ($component['variant']->breadth ?? 0)),
                'length' => $components->max(fn ($component) => (float) ($component['variant']->length ?? 0)),
                'availability' => true,
                'provider' => 'self',
                'barcode' => $variant->barcode ?: strtoupper(Str::random(13)),
                'sku' => trim($data['sku'] ?? '') ?: 'PACK-'.$product->id,
                'price' => (float) $data['price'],
                'special_price' => 0,
                'visibility' => 'published',
                'is_default' => true,
            ])->save();

            if ($isNew && $firstProduct->getFirstMedia(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE())) {
                $firstProduct->getFirstMedia(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE())
                    ->copy($product, SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
            }

            $comboPack = ComboPack::updateOrCreate(
                ['product_id' => $product->id],
                ['store_id' => $store->id],
            );
            Cache::forget('combo-pack-product:'.$product->id);
            $comboPack->items()->delete();
            foreach ($components as $component) {
                ComboPackItem::create([
                    'combo_pack_id' => $comboPack->id,
                    'product_id' => $component['variant']->product_id,
                    'product_variant_id' => $component['variant']->id,
                    'quantity' => $component['quantity'],
                ]);
            }

            $storeVariant = StoreProductVariant::firstOrCreate([
                'store_id' => $store->id,
                'product_variant_id' => $variant->id,
            ]);
            $locations = StoreLocation::query()->where('store_id', $store->id)->where('is_active', true)->get();
            foreach ($locations as $location) {
                StoreLocationInventory::updateOrCreate(
                    ['store_location_id' => $location->id, 'product_variant_id' => $variant->id],
                    [
                        'is_fulfillment_location' => (bool) $location->fulfills_online_orders,
                        'tracked' => false,
                        'stock' => 0,
                        'reserved' => 0,
                        'low_stock_threshold' => 0,
                    ],
                );
            }

            return $comboPack->load(['product', 'store', 'items.product', 'items.variant']);
        });
    }
}
