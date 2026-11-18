<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Global store scope for the SELLER panel — the seller analog of the admin's
 * market scope. A seller can own several stores (Seller hasMany Store); the
 * seller panel is scoped to exactly one of them at a time, chosen from the
 * header selector and persisted in the session.
 *
 * Every resolution is bounded to the LOGGED-IN seller's own stores, so a
 * seller can never scope the panel to a store they don't own.
 */
class SellerStoreService
{
    public const SESSION_KEY = 'seller_store_id';

    public const HEADER_KEY = 'X-Store-Id';

    protected ?int $overrideId = null;

    /**
     * Stores owned by the currently authenticated seller, ordered by id.
     * Empty collection if the user isn't a seller or has no stores yet.
     *
     * @return Collection<int, Store>
     */
    public function storesForCurrentSeller(): Collection
    {
        $user = Auth::user();
        $seller = $user?->seller();
        if (!$seller) {
            return collect();
        }

        $stores = $seller->stores()->orderBy('id')->get();

        return $stores->filter(fn (Store $store) => $this->userMayAccessStore($user, $seller, $store))
            ->values();
    }

    /**
     * The seller OWNER (sellers.user_id) is never restricted. A staff sub-user
     * is limited to their `store_user` assignments — but an empty assignment set
     * means "no restriction", so existing staff keep full access until stores
     * are explicitly assigned to them.
     */
    protected function userMayAccessStore($user, $seller, Store $store): bool
    {
        if ((int) $seller->user_id === (int) $user->id) {
            return true;
        }

        $assignedIds = $user->assignedStores()->pluck('stores.id');
        if ($assignedIds->isEmpty()) {
            return true;
        }

        return $assignedIds->contains($store->id);
    }

    /**
     * The store the seller panel is currently scoped to. Validates the session
     * selection against the seller's own stores and otherwise falls back to
     * their first store (self-healing the session). Null when the seller has
     * no stores.
     */
    public function selectedStore(): ?Store
    {
        $stores = $this->storesForCurrentSeller();
        if ($stores->isEmpty()) {
            return null;
        }

        if ($this->overrideId !== null) {
            $override = $stores->firstWhere('id', $this->overrideId);
            if ($override instanceof Store) {
                return $override;
            }
        }

        $sessionId = session(self::SESSION_KEY);
        $store = $sessionId ? $stores->firstWhere('id', (int) $sessionId) : null;

        if (!$store instanceof Store) {
            $store = $stores->first();
            session([self::SESSION_KEY => $store->id]);
        }

        return $store;
    }

    /** Convenience id accessor for controllers/queries; null when no store. */
    public function selectedId(): ?int
    {
        return $this->selectedStore()?->id;
    }

    /**
     * IDs of the stores the current seller (or scoped staff user) may access.
     * Use to constrain any panel query that would otherwise enumerate every
     * store the seller owns, so staff see only their assigned stores.
     *
     * @return array<int, int>
     */
    public function accessibleStoreIds(): array
    {
        return $this->storesForCurrentSeller()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Persist the seller's store selection — but only if that store belongs to
     * them. Returns the store actually set, or null if the id wasn't theirs.
     */
    public function setStore(int $storeId): ?Store
    {
        $store = $this->storesForCurrentSeller()->firstWhere('id', $storeId);

        if ($store instanceof Store) {
            session([self::SESSION_KEY => $store->id]);
        }

        return $store;
    }

    /**
     * Stateless store scope used by the seller API. Pins the selection for the
     * lifetime of the request only — no session write — so a mobile client can
     * scope every call with the `X-Store-Id` header instead of a cookie.
     * Returns null when the id isn't one of the seller's own stores.
     */
    public function useStore(int $storeId): ?Store
    {
        $store = $this->storesForCurrentSeller()->firstWhere('id', $storeId);

        if ($store instanceof Store) {
            $this->overrideId = $store->id;
        }

        return $store;
    }

    /** Pin the seller's first store for this request without touching the session. */
    public function useDefaultStore(): ?Store
    {
        $store = $this->storesForCurrentSeller()->first();

        if ($store instanceof Store) {
            $this->overrideId = $store->id;
        }

        return $store;
    }
}
