<?php

namespace App\Services;

use App\Models\ShippingProfile;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ShippingProfile CRUD + the attachment plumbing.
 *
 * Owns three things that callers shouldn't reimplement:
 *   1. Slug generation (uniqueness scoped per-store; the default profile
 *      always wins the 'general' slug, custom profiles get suffixed copies).
 *   2. Location attachment via the shipping_profile_store_location pivot —
 *      sync semantics so the UI's multi-select round-trips cleanly.
 *   3. Product attachment via shipping_profile_products. Because the table
 *      has UNIQUE(product_id), syncing here can collide with another
 *      profile that already holds the product — we detach from other
 *      profiles in the same store first, mirroring Shopify's "move"
 *      semantics rather than throwing.
 *
 * Default-profile invariants (no delete, no unset of is_default) are owned
 * by ShippingProfileObserver and are not reimplemented here.
 */
class ShippingProfileService
{
    public function create(Store $store, array $data): ShippingProfile
    {
        return DB::transaction(function () use ($store, $data) {
            $profile = $store->shippingProfiles()->create([
                'name'       => $data['name'],
                'slug'       => $this->generateSlug($store, $data['name']),
                'is_default' => false,
            ]);

            $this->syncLocations($profile, $data['store_location_ids'] ?? [], $data['store_location_priority'] ?? []);
            $this->syncProducts($profile, $data['product_ids'] ?? []);

            return $profile->fresh(['locations', 'products']);
        });
    }

    public function update(ShippingProfile $profile, array $data): ShippingProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            if (!$profile->is_default && array_key_exists('name', $data)) {
                $profile->fill(['name' => $data['name']])->save();
            }

            $this->syncLocations($profile, $data['store_location_ids'] ?? [], $data['store_location_priority'] ?? []);

            if (!$profile->is_default) {
                $this->syncProducts($profile, $data['product_ids'] ?? []);
            }

            return $profile->fresh(['locations', 'products']);
        });
    }

    /**
     * Delete a non-default profile. Observer rejects default deletion so the
     * rule is enforced even if a caller bypasses this service.
     */
    public function delete(ShippingProfile $profile): void
    {
        DB::transaction(function () use ($profile) {
            $profile->locations()->detach();
            $profile->products()->detach();
            $profile->delete();
        });
    }

    /**
     * Replace the location attachments. IDs not owned by the same store are
     * dropped silently — defense in depth against tampered form payloads.
     *
     * `$priorityOrder` is the seller's manual ranking (location ids in the
     * order they should fulfill — "Use ranked locations"). Lower priority wins
     * in FulfillmentRouterService::pickLocation(). Locations omitted from the
     * order keep priority 0, so an empty/absent order is fully backward compatible.
     */
    protected function syncLocations(ShippingProfile $profile, array $locationIds, array $priorityOrder = []): void
    {
        $ids = collect($locationIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $validIds = $profile->store->locations()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $profile->locations()->sync($validIds);

        $ordered = collect($priorityOrder)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->filter(fn ($id) => in_array($id, $validIds, true))
            ->values();

        if ($ordered->isNotEmpty()) {
            $rest = collect($validIds)->reject(fn ($id) => $ordered->contains($id))->values();
            $finalOrder = $ordered->concat($rest);

            foreach ($finalOrder as $index => $id) {
                $profile->locations()->updateExistingPivot($id, ['priority' => $index + 1]);
            }
        }
    }

    /**
     * Replace product attachments. shipping_profile_products has UNIQUE on
     * product_id, so a given product can only sit in one profile at a time.
     * We detach any conflicting rows from sibling profiles before syncing
     * (Shopify treats this as "move", not a hard error).
     *
     * Products are seller-owned (no store_id on products) — same scoping
     * as the seller products screen. The store-level filter that used to
     * walk variants.storeProductVariants was removed; the only ownership
     * check is `seller_id`.
     */
    protected function syncProducts(ShippingProfile $profile, array $productIds): void
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sellerId = $profile->store?->seller_id;
        $allowed = $sellerId
            ? \App\Models\Product::query()
                ->where('seller_id', $sellerId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all()
            : [];

        if (!empty($allowed)) {
            DB::table('shipping_profile_products')
                ->whereIn('product_id', $allowed)
                ->where('shipping_profile_id', '!=', $profile->id)
                ->delete();
        }

        $profile->products()->sync($allowed);
    }

    /**
     * Per-store unique slug. Default profile reserves 'general'; everything
     * else slugifies the name and disambiguates with -2, -3, … if needed.
     */
    protected function generateSlug(Store $store, string $name): string
    {
        $base = Str::slug($name) ?: 'profile';
        $candidate = $base;
        $suffix = 2;

        while ($store->shippingProfiles()->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }
}
