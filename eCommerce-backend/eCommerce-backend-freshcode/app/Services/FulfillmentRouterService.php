<?php

namespace App\Services;

use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ServiceabilityMatchTypeEnum;
use App\Enums\Shipping\ServiceabilityModeEnum;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShippingProfile;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRate;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Services\Shipping\Providers\ShiprocketProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fulfillment router — Shopify-style line-to-location matching.
 *
 * Per cart line:
 *   1. Resolve the line's shipping profile (own profile, or store's default)
 *   2. Get the profile's allowed store_locations
 *   3. Filter to locations with stock for the variant (store_location_inventory)
 *   4. Find ALL zones whose country coverage includes the destination, ordered
 *      by `priority` ASC.
 *   5. Walk zones in that order — skip any manual zone whose serviceability
 *      rules exclude the destination (deny match / allow miss), then pick the
 *      first remaining zone whose rate rules produce a non-null rate for the
 *      cart context (weight / price / flat).
 *      This is the "zone priority fallback": if Zone A covers India but its
 *      weight tiers don't match the item's weight, we fall through to Zone B
 *      (next priority), which may have a flat rate that does apply.
 *   6. Tiebreak location: prefer the store's default location, else lowest id.
 *   7. Return per-line { location, zone, rate } or { unfulfillable: reason }
 *
 * Multi-item carts are NOT merged into a single shipment here — that's a
 * shipping-aggregation concern. This service returns one decision per line;
 * the caller can group by location to count shipments.
 *
 * Destination shape (passed in):
 *   [
 *     'country_iso2' => 'IN',
 *     'state_code'   => 'MH',          // string or null, for serviceability
 *     'city_name'    => 'Mumbai',      // string or null, for serviceability
 *     'pin_code'     => '400001',      // string or null, for serviceability
 *     'cart_total'   => 1499.00,       // for free_above + price_rules
 *     'cart_weight'  => 0.8,           // kg, for weight_rules
 *   ]
 */
class FulfillmentRouterService
{
    protected ?array $lastQuoteMeta = null;

    /**
     * Decide fulfillment for every line in a cart.
     *
     * @return Collection<int, array{
     *   cart_item_id: int,
     *   location: ?StoreLocation,
     *   zone: ?ShippingZone,
     *   rate: ?float,
     *   eta_min: ?int,
     *   eta_max: ?int,
     *   eta_unit: ?string,
     *   unfulfillable: ?string,
     * }>
     */
    public function routeCart(iterable $cartItems, array $destination): Collection
    {
        $decisions = collect();
        foreach ($cartItems as $item) {
            $decisions->push($this->routeLine($item, $destination));
        }
        return $decisions;
    }

    /**
     * Compute the shipping rate for a single zone against an arbitrary context
     * (`cart_total` / `cart_weight`). Public wrapper around pickRate() +
     * computeRate() so callers can re-price a *shipment group* using the
     * group's own combined weight/total instead of the whole cart's.
     *
     * Returns null when the zone has no active rate row, or its rules don't
     * cover the supplied context (e.g. weight outside every tier).
     */
    public function rateForZone(ShippingZone $zone, array $context): ?float
    {
        $rateRow = $this->pickRate($zone);
        if (!$rateRow) {
            return null;
        }
        return $this->computeRate($rateRow, $context);
    }

    /**
     * Aggregate per-line routing decisions into Shopify-style shipment groups
     * and price each group ONCE.
     *
     * A shipment group is one (store_location_id, shipping_zone_id) pair: lines
     * that resolve to the same origin location and zone ship together, so the
     * zone's rate is charged a single time for the whole group — not once per
     * line. This is the fix for "2 items, same zone, flat ₹50 → ₹100"; the
     * correct charge is ₹50.
     *
     *   - flat zones          : the flat rate is charged once.
     *   - weight/price zones   : the rate is RE-COMPUTED from the group's own
     *                            combined weight / total, so a cart that splits
     *                            across zones is priced per shipment (not by the
     *                            whole-cart weight the per-line pass used).
     *
     * Only fulfillable lines (decision.unfulfillable === null) are grouped;
     * unfulfillable lines never contribute a charge. If a group's re-priced
     * rate comes back null (group context no longer matches a tier), we fall
     * back to the per-line rate the router already accepted, so this is never
     * a regression.
     *
     * @param  Collection<int,CartItem> $items     The cart items to group.
     * @param  Collection              $decisions  Output of routeCart() (keyed or not).
     * @return Collection<string,array{
     *   location: ?StoreLocation,
     *   zone: ?ShippingZone,
     *   location_id: ?int,
     *   zone_id: ?int,
     *   rate: float,
     *   items: Collection<int,CartItem>
     * }>
     */
    public function groupShipments(Collection $items, Collection $decisions): Collection
    {
        $decisionsByItem = $decisions->keyBy('cart_item_id');
        $groups = [];

        foreach ($items as $item) {
            $decision = $decisionsByItem->get($item->id);

            if (!$decision || ($decision['unfulfillable'] ?? null) !== null) {
                continue;
            }

            $locationId = $decision['location'] ? (int) $decision['location']->id : null;
            $zoneId     = $decision['zone'] ? (int) $decision['zone']->id : null;
            $key = $locationId . ':' . $zoneId;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'location'    => $decision['location'] ?? null,
                    'zone'        => $decision['zone'] ?? null,
                    'location_id' => $locationId,
                    'zone_id'     => $zoneId,
                    'items'       => collect(),
                    '_line_rate'  => (float) ($decision['rate'] ?? 0.0),
                ];
            }
            $groups[$key]['items']->push($item);
        }
        return collect($groups)->map(function (array $group) {
            $zone = $group['zone'];
            $rate = $group['_line_rate'];

            if ($zone) {
                $groupTotal  = (float) $group['items']->sum(fn ($i) => $this->lineTotal($i));
                $groupWeight = (float) $group['items']->sum(
                    fn ($i) => (float) ($i->variant->weight ?? 0.0) * (int) $i->quantity
                );

                $recomputed = $this->rateForZone($zone, [
                    'cart_total'  => $groupTotal,
                    'cart_weight' => $groupWeight,
                ]);
                $rate = $recomputed !== null ? $recomputed : $group['_line_rate'];
            }

            $group['rate'] = (float) $rate;
            unset($group['_line_rate']);

            return $group;
        });
    }

    /**
     * Effective line total used when re-pricing a shipment group. Prefers the
     * line total already stamped onto the cart item by
     * CartService::calculateCartTotals (`->price`, which includes addons);
     * falls back to resolving the store-level variant price + addons so the
     * method is safe to call on a freshly loaded cart too.
     */
    protected function lineTotal(CartItem $item): float
    {
        if (isset($item->price) && (float) $item->price > 0) {
            return (float) $item->price;
        }

        $variant = $item->variant;
        if (!$variant) {
            return 0.0;
        }

        $storeVariant = $variant->relationLoaded('storeProductVariants')
            ? $variant->storeProductVariants->where('store_id', $item->store_id)->first()
            : null;
        $source = $storeVariant ?: $variant;

        $unit = (float) ($source->special_price ?? 0) > 0
            ? (float) $source->special_price
            : (float) ($source->price ?? 0);

        $addonsPerUnit = (float) ($item->relationLoaded('addons')
            ? $item->getRelation('addons')->sum('price')
            : 0.0);

        return ($unit + $addonsPerUnit) * (int) $item->quantity;
    }

    /**
     * Decide fulfillment for a single cart line.
     *
     * Zone-priority fallback: we walk ALL zones that geographically cover the
     * destination (in priority order). For each zone we attempt to compute a
     * rate. The first zone that yields a non-null rate wins. This means:
     *
     *   Zone "India"   priority=1  weight-based: tiers 3-5 kg = ₹50
     *   Zone "India 2" priority=2  flat: ₹100
     *
     *   Item 4 kg  → India matches weight tier  → ₹50 ✓
     *   Item 1 kg  → India has no tier for 1 kg → null → fall through
     *              → India 2 flat rate           → ₹100 ✓
     *   Item 1 kg, India 2 not on profile        → no_rate_for_destination
     */

    function baseDecision($itemId, $destination = null): array
    {
       return [
            'cart_item_id'   => $itemId,
            'location'       => null,
            'zone'           => null,
            'rate'           => null,
            'eta_min'        => null,
            'eta_max'        => null,
            'eta_unit'       => null,
            'unfulfillable'  => $destination,
        ];
    }
    public function routeLine(CartItem $item, array $destination): array
    {
        $product = $item->product;
        $variantId = (int) $item->product_variant_id;
        $qty = (int) ($item->quantity ?? 1);
        $store = $item->store;

        if (!$product || !$store) {
            return $this->baseDecision($item->id, 'invalid_product_or_store');
        }

        $profile = $this->resolveShippingProfile($product, $store);
        if (!$profile) {
            return $this->baseDecision($item->id, 'no_shipping_profile');
        }

        $allowedLocations = $profile->locations()
            ->where('store_locations.is_active', true)
            ->where('store_locations.fulfills_online_orders', true)
            ->get();
        if ($allowedLocations->isEmpty()) {
            return $this->baseDecision($item->id, 'no_location_in_profile');
        }

        $allowedIds = $allowedLocations->pluck('id');
        $stockedIds = DB::table('store_location_inventory')
            ->whereIn('store_location_id', $allowedIds)
            ->where('product_variant_id', $variantId)
            ->where(function ($q) use ($qty) {
                $q->where('tracked', false)
                  ->orWhereRaw('(stock - reserved) >= ?', [$qty]);
            })
            ->pluck('store_location_id');

        $stockedLocations = $allowedLocations->whereIn('id', $stockedIds);
        if ($product->comboPack) {
            $product->comboPack->loadMissing('items');
            $stockedLocations = $stockedLocations->filter(function (StoreLocation $location) use ($product, $qty) {
                foreach ($product->comboPack->items as $component) {
                    $inventory = DB::table('store_location_inventory')
                        ->where('store_location_id', $location->id)
                        ->where('product_variant_id', $component->product_variant_id)
                        ->first(['tracked', 'stock', 'reserved', 'is_fulfillment_location']);
                    if (! $inventory || ! $inventory->is_fulfillment_location) {
                        return false;
                    }
                    if ($inventory->tracked && ((int) $inventory->stock - (int) $inventory->reserved) < $qty * (int) $component->quantity) {
                        return false;
                    }
                }

                return true;
            });
        }
        if ($stockedLocations->isEmpty()) {
            return $this->baseDecision($item->id, 'out_of_stock_at_allowed_locations');
        }

        $matchedZones = $this->findMatchingZonesOrdered($profile, $destination);
        if ($matchedZones->isEmpty()) {
            return $this->baseDecision($item->id, 'no_zone_covers_destination');
        }

        $picked = $this->pickLocation($stockedLocations, $store);

        $rateContext = $destination;
        $rateContext['pickup_pin_code'] = $picked?->pin_code;

        $acceptedZone = null;
        $acceptedRate = null;
        $acceptedRateRow = null;
        $acceptedMeta = null;

        foreach ($matchedZones as $zone) {
            if (!$this->passesServiceability($zone, $destination)) {
                continue;
            }
            $rateRow = $this->pickRate($zone);
            if (!$rateRow) {
                continue;
            }
            $this->lastQuoteMeta = null;
            $rate = $this->computeRate($rateRow, $rateContext);
            if ($rate === null) {
                continue;
            }
            $acceptedZone    = $zone;
            $acceptedRate    = $rate;
            $acceptedRateRow = $rateRow;
            $acceptedMeta    = $this->lastQuoteMeta;
            break;
        }

        if (!$acceptedZone) {
            return $this->baseDecision($item->id, 'no_rate_for_destination');
        }

        return [
            'cart_item_id'  => $item->id,
            'location'      => $picked,
            'zone'          => $acceptedZone,
            'rate'          => $acceptedRate,
            'eta_min'       => $acceptedMeta['eta_min'] ?? $acceptedRateRow->eta_min,
            'eta_max'       => $acceptedMeta['eta_max'] ?? $acceptedRateRow->eta_max,
            'eta_unit'      => $acceptedMeta['eta_unit'] ?? $acceptedRateRow->eta_unit,
            'unfulfillable' => null,
        ];
    }

    /**
     * Choose which rate row applies. Single-rate phase = first active rate
     * by priority. Multi-rate will switch this to a customer-visible picker.
     */
    protected function pickRate(ShippingZone $zone)
    {
        return $zone->rates()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * Resolve the shipping profile for a product. Returns the product's own
     * profile if assigned, otherwise the store's default ("General profile").
     */
    public function resolveShippingProfile(Product $product, Store $store): ?ShippingProfile
    {
        $own = $product->shippingProfile()->first();
        if ($own && (int) $own->store_id === (int) $store->id) {
            return $own;
        }
        return $store->defaultShippingProfile()->with('locations')->first();
    }

    /**
     * Resolve the delivery ETA for a shipping profile shipped to a destination.
     *
     * Walks the profile's zones that cover the destination (city > state >
     * country specificity) and returns the ETA of the first zone that has an
     * active rate carrying one. Country-only destinations are fine — the
     * broadest (country) match applies.
     *
     * @return array{min: ?int, max: ?int, unit: string}|null Null when no zone/rate/ETA resolves.
     */
    public function resolveEtaForProfile(ShippingProfile $profile, string $countryIso2, ?string $state = null, ?string $city = null): ?array
    {
        $destination = [
            'country_iso2' => strtoupper($countryIso2),
            'state_code'   => $state,
            'city_name'    => $city,
        ];

        foreach ($this->findMatchingZonesOrdered($profile, $destination) as $zone) {
            $rateRow = $this->pickRate($zone);
            if (!$rateRow) {
                continue;
            }
            if ($rateRow->eta_min === null && $rateRow->eta_max === null) {
                continue;
            }

            return [
                'min'  => $rateRow->eta_min !== null ? (int) $rateRow->eta_min : null,
                'max'  => $rateRow->eta_max !== null ? (int) $rateRow->eta_max : null,
                'unit' => $rateRow->eta_unit ?? 'days',
            ];
        }

        return null;
    }

    /**
     * Resolve the delivery ETA for a product shipped to a given country — used
     * on the product-detail screen, where only a country (no full address) is
     * known. Anchors the profile lookup on a store: the one passed in, else the
     * product's primary (nearest) store from its loaded variants. Returns null
     * when no profile/zone/ETA resolves.
     *
     * @return array{min: ?int, max: ?int, unit: string}|null
     */
    public function resolveProductEta(Product $product, string $countryIso2, ?Store $store = null, ?string $state = null, ?string $city = null): ?array
    {
        if (!$store) {
            $store = optional(optional($product->variants->first())->storeProductVariants->first())->store;
        }

        $profile = $store
            ? $this->resolveShippingProfile($product, $store)
            : $product->shippingProfile()->first();

        if (!$profile) {
            return null;
        }

        return $this->resolveEtaForProfile($profile, $countryIso2, $state, $city);
    }

    /**
     * Resolve a truthful fixed shipping offer for product discovery markup.
     * Conditional carrier, weight, and price rates are deliberately omitted.
     *
     * @return array{country: string, rate: float, eta_min: ?int, eta_max: ?int, eta_unit: string}|null
     */
    public function resolveProductShippingOffer(Product $product, string $countryIso2, ?Store $store = null): ?array
    {
        return $this->resolveProductShippingOffers($product, [$countryIso2], $store)[0] ?? null;
    }

    /**
     * Resolve fixed discovery-feed shipping offers for multiple countries.
     * Zones and rates are loaded once so feed generation scales by product,
     * rather than issuing a full shipping lookup for every target country.
     *
     * @param  array<int, string>  $countryIso2s
     * @return array<int, array{country: string, rate: float, eta_min: ?int, eta_max: ?int, eta_unit: string}>
     */
    public function resolveProductShippingOffers(Product $product, array $countryIso2s, ?Store $store = null): array
    {
        $countries = collect($countryIso2s)
            ->map(fn ($country) => strtoupper(trim((string) $country)))
            ->filter(fn ($country) => preg_match('/^[A-Z]{2}$/', $country) === 1)
            ->unique()
            ->take(100)
            ->values();

        if ($countries->isEmpty()) {
            return [];
        }

        if (! $store) {
            $store = optional(optional($product->variants->first())->storeProductVariants->first())->store;
        }

        $profile = $store
            ? $this->resolveShippingProfile($product, $store)
            : $product->shippingProfile()->first();

        if (! $profile) {
            return [];
        }

        $zones = ShippingZone::query()
            ->where('shipping_profile_id', $profile->id)
            ->where('is_active', true)
            ->whereIn('method', $this->enabledZoneMethods())
            ->whereHas('countries', fn ($query) => $query->whereIn('iso2', $countries->all()))
            ->with([
                'countries' => fn ($query) => $query->whereIn('iso2', $countries->all()),
                'rates' => fn ($query) => $query->where('is_active', true)
                    ->orderBy('priority')
                    ->orderBy('id'),
            ])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $countries->map(function (string $country) use ($zones) {
            foreach ($zones as $zone) {
                if (! $zone->countries->contains(fn ($covered) => strtoupper((string) $covered->iso2) === $country)) {
                    continue;
                }

                $rate = $zone->rates->first();
                if (! $rate || $rate->rate_type !== 'flat' || $rate->flat_rate === null) {
                    continue;
                }

                return [
                    'country' => $country,
                    'rate' => (float) $rate->flat_rate,
                    'eta_min' => $rate->eta_min !== null ? (int) $rate->eta_min : null,
                    'eta_max' => $rate->eta_max !== null ? (int) $rate->eta_max : null,
                    'eta_unit' => $rate->eta_unit ?? 'days',
                ];
            }

            return null;
        })->filter()->values()->all();
    }

    /**
     * Find ALL active zones on the profile whose country coverage includes the
     * destination country, ordered by `priority` ASC then `id`.
     *
     * The router iterates this collection and picks the first zone that both
     * passes serviceability and yields a non-null rate, giving the zone-priority
     * fallback behaviour described in the class docblock.
     *
     * @return Collection<int, ShippingZone>
     */
    protected function findMatchingZonesOrdered(ShippingProfile $profile, array $destination): Collection
    {
        $country = $destination['country_iso2'] ?? null;

        if (!$country) {
            return collect();
        }

        return ShippingZone::where('shipping_profile_id', $profile->id)
            ->where('is_active', true)
            ->whereIn('method', $this->enabledZoneMethods())
            ->whereHas('countries', fn ($q) => $q->where('iso2', $country))
            ->with('serviceabilityRules')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * Decide whether a zone will serve the destination, honouring its
     * serviceability mode. Only manual zones are gated — carrier zones
     * (Shiprocket) get serviceability from the courier API, so they always
     * pass here.
     *
     * Rules are scoped per destination country: only rules whose `country_iso2`
     * equals the destination country are considered. A country with no rules is
     * unrestricted (passes in every mode), so an allow/deny list on one country
     * never blocks a different covered country.
     *
     *   - NONE  : always passes.
     *   - DENY  : passes unless a rule matches (blocklist).
     *   - ALLOW : passes only if a rule matches (allowlist).
     */
    protected function passesServiceability(ShippingZone $zone, array $destination): bool
    {
        if ((string) $zone->method !== FulfillmentMethodEnum::MANUAL->value) {
            return true;
        }

        $mode = (string) ($zone->serviceability_mode ?? ServiceabilityModeEnum::NONE->value);
        if ($mode === ServiceabilityModeEnum::NONE->value) {
            return true;
        }

        $country = strtoupper((string) ($destination['country_iso2'] ?? ''));
        $rules = $zone->serviceabilityRules
            ->filter(fn ($r) => strtoupper((string) $r->country_iso2) === $country);

        if ($rules->isEmpty()) {
            return true;
        }

        $matched = $rules->contains(fn ($r) => $this->serviceabilityRuleMatches($r, $destination));

        return $mode === ServiceabilityModeEnum::ALLOW->value ? $matched : !$matched;
    }

    /**
     * Whether one serviceability rule matches the destination. Pincode rules
     * normalise both sides (uppercase, strip spaces) so UK-style codes compare
     * cleanly; city/state rules slug-compare so case/spacing never blocks a
     * match. A range rule needs numeric endpoints on both sides.
     */
    protected function serviceabilityRuleMatches($rule, array $destination): bool
    {
        $type = (string) $rule->match_type;

        if (in_array($type, [
            ServiceabilityMatchTypeEnum::PINCODE_EXACT->value,
            ServiceabilityMatchTypeEnum::PINCODE_PREFIX->value,
            ServiceabilityMatchTypeEnum::PINCODE_RANGE->value,
        ], true)) {
            $pin = $this->normalizePincode($destination['pin_code'] ?? '');
            if ($pin === '') {
                return false;
            }
            $value = $this->normalizePincode($rule->value);

            return match ($type) {
                ServiceabilityMatchTypeEnum::PINCODE_EXACT->value  => $pin === $value,
                ServiceabilityMatchTypeEnum::PINCODE_PREFIX->value => $value !== '' && str_starts_with($pin, $value),
                ServiceabilityMatchTypeEnum::PINCODE_RANGE->value  => $this->pincodeInRange($pin, $value, $this->normalizePincode($rule->value_to ?? '')),
                default => false,
            };
        }

        $field = $type === ServiceabilityMatchTypeEnum::STATE->value
            ? ($destination['state_code'] ?? '')
            : ($destination['city_name'] ?? '');
        $left = Str::slug((string) $field);
        $right = Str::slug((string) $rule->value);

        return $left !== '' && $left === $right;
    }

    protected function normalizePincode($value): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $value));
    }

    protected function pincodeInRange(string $pin, string $from, string $to): bool
    {
        if (!is_numeric($pin) || !is_numeric($from) || !is_numeric($to)) {
            return false;
        }
        $lo = min((float) $from, (float) $to);
        $hi = max((float) $from, (float) $to);

        return (float) $pin >= $lo && (float) $pin <= $hi;
    }

    /**
     * @return array<int, string>
     */
    protected function enabledZoneMethods(): array
    {
        $config = app(SettingService::class)->shiprocketConfig();

        $methods = [];
        if ((bool) ($config['manualEnabled'] ?? true)) {
            $methods[] = FulfillmentMethodEnum::MANUAL->value;
        }
        if ((bool) ($config['shiprocketEnabled'] ?? false)) {
            $methods[] = FulfillmentMethodEnum::SHIPROCKET->value;
        }

        return $methods;
    }

    /**
     * Tiebreak location selection. Order of preference:
     *   1. Lower pivot `priority` (manual "ranked locations" — Shopify-style)
     *   2. The store's default location
     *   3. Lowest id (deterministic)
     *
     * Equal priority (the default 0 for un-ranked rows) falls through to the
     * previous default→id behaviour, so this is backward compatible.
     */
    protected function pickLocation(Collection $candidates, Store $store): StoreLocation
    {
        return $candidates->sort(function (StoreLocation $a, StoreLocation $b) {
            $pa = (int) ($a->pivot->priority ?? 0);
            $pb = (int) ($b->pivot->priority ?? 0);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $da = $a->is_default ? 0 : 1;
            $db = $b->is_default ? 0 : 1;
            if ($da !== $db) {
                return $da <=> $db;
            }
            return $a->id <=> $b->id;
        })->first();
    }

    /**
     * Apply a rate row against the destination context. Reads from
     * `shipping_zone_rates` columns; returns null when no rule matches
     * (e.g. item weight outside all weight tiers) so the caller can fall
     * through to the next zone in priority order.
     *
     * Note: `free_above` is checked first — a cart total above the threshold
     * always yields 0.0 regardless of the rate type.
     */
    protected function computeRate(ShippingZoneRate $rate, array $destination): ?float
    {
        $cartTotal  = (float) ($destination['cart_total']  ?? 0);
        $cartWeight = (float) ($destination['cart_weight'] ?? 0);

        if ($rate->free_above !== null && (float) $rate->free_above > 0 && $cartTotal >= (float) $rate->free_above) {
            return 0.0;
        }

        return match ($rate->rate_type) {
            'flat'           => (float) ($rate->flat_rate ?? 0),
            'weight_based'   => $this->resolveTier($rate->weight_rules ?? [], $cartWeight, 'min_kg', 'max_kg'),
            'price_based'    => $this->resolveTier($rate->price_rules ?? [], $cartTotal, 'min_amount', 'max_amount'),
            'carrier_quoted' => $this->carrierQuotedRate($destination),
            default          => null,
        };
    }

    protected function carrierQuotedRate(array $context): ?float
    {
        $pickupPin = $context['pickup_pin_code'] ?? null;
        $destPin   = $context['pin_code'] ?? null;
        if (blank($pickupPin) || blank($destPin)) {
            return null;
        }

        $result = app(ShiprocketProvider::class)->checkoutRate(
            (string) $pickupPin,
            (string) $destPin,
            (float) ($context['cart_weight'] ?? 0),
            (float) ($context['cart_total'] ?? 0),
            (bool) ($context['cod'] ?? false),
        );
        if ($result === null) {
            return null;
        }

        $this->lastQuoteMeta = [
            'eta_min'  => $result['eta_min'],
            'eta_max'  => $result['eta_max'],
            'eta_unit' => $result['eta_unit'],
        ];

        return (float) $result['rate'];
    }

    /**
     * Pick the first tier whose [min, max] range covers $value. Tier keys
     * are domain-specific (`min_kg`/`max_kg` for weight, `min_amount`/
     * `max_amount` for price) — caller passes them in so this stays generic.
     *
     * Returns null (not 0) when no tier matches, so the router can distinguish
     * "no applicable tier" from "tier matched with a zero rate".
     */
    protected function resolveTier(array $rules, float $value, string $minKey, string $maxKey): ?float
    {
        $best = null;
        $bestSpan = null;

        foreach ($rules as $rule) {
            $min = (float) ($rule[$minKey] ?? 0);
            $max = isset($rule[$maxKey]) ? (float) $rule[$maxKey] : null;

            if ($value < $min || ($max !== null && $value > $max)) {
                continue;
            }

            $span = $max === null ? INF : ($max - $min);

            if ($bestSpan === null || $span < $bestSpan) {
                $best = (float) ($rule['rate'] ?? 0);
                $bestSpan = $span;
            }
        }

        return $best;
    }
}
