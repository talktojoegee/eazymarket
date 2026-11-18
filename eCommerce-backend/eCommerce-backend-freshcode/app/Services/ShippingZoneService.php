<?php

namespace App\Services;

use App\Enums\Shipping\EtaUnitEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\RateTypeEnum;
use App\Enums\Shipping\ServiceabilityMatchTypeEnum;
use App\Enums\Shipping\ServiceabilityModeEnum;
use App\Models\ShippingProfile;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRate;
use App\Models\ShippingZoneServiceabilityRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ShippingZone CRUD + coverage + rate plumbing.
 *
 * After the Shopify-style refactor, a zone owns N rates (one per row in
 * `shipping_zone_rates`). The seller UI lets sellers manage exactly one
 * rate per zone today; storing them in their own table now means the
 * multi-rate UX is a pure frontend change later.
 *
 * Responsibilities owned here:
 *   1. Splitting the inbound payload into zone-level fields vs rate-level
 *      fields (rate_type / flat_rate / weight_rules / etc.).
 *   2. Rate-type-aware coercion — nulls out flat_rate when not applicable,
 *      sanitises the JSON tier shapes for weight/price.
 *   3. Coverage sync — countries M:M.
 *   4. Serviceability sync — deny/allow rules (pincode/city/state) HasMany.
 */
class ShippingZoneService
{
    public function create(ShippingProfile $profile, array $data): ShippingZone
    {
        return DB::transaction(function () use ($profile, $data) {
            $zone = $profile->zones()->create($this->buildZonePayload($data));
            $zone->rates()->create($this->buildRatePayload($data, $zone));

            $this->syncCoverage($zone, $data);

            return $zone->fresh(['countries', 'serviceabilityRules', 'rates']);
        });
    }

    public function update(ShippingZone $zone, array $data): ShippingZone
    {
        return DB::transaction(function () use ($zone, $data) {
            $zone->fill($this->buildZonePayload($data))->save();

            $rate = $zone->defaultRate()->first();
            if ($rate) {
                $rate->fill($this->buildRatePayload($data, $zone))->save();
            } else {
                $zone->rates()->create($this->buildRatePayload($data, $zone));
            }

            $this->syncCoverage($zone, $data);

            return $zone->fresh(['countries', 'serviceabilityRules', 'rates']);
        });
    }

    public function delete(ShippingZone $zone): void
    {
        DB::transaction(function () use ($zone) {
            $zone->countries()->detach();
            $zone->serviceabilityRules()->delete();
            $zone->rates()->each(fn (ShippingZoneRate $r) => $r->delete());
            $zone->delete();
        });
    }

    /**
     * Fields that live on the zone row itself — identity + coverage flags.
     * Rate/ETA fields used to live here too; they moved to shipping_zone_rates
     * in the Shopify-parity refactor, but we still write the legacy columns
     * with sane defaults so any code that hasn't been migrated keeps working.
     */
    protected function buildZonePayload(array $data): array
    {
        $method = $data['method'] ?? FulfillmentMethodEnum::MANUAL->value;

        $rateType = $data['rate_type'] ?? RateTypeEnum::FLAT->value;

        return [
            'name'            => $data['name'] ?? null,
            'method'          => $method,
            'priority'        => $this->intOrNull($data['priority'] ?? null) ?? 0,
            'is_active'       => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'serviceability_mode' => $this->resolveServiceabilityMode($data),
            'operating_hours' => $data['operating_hours'] ?? null,

            'rate_type'  => $rateType,
            'flat_rate'  => $this->resolveFlatRate($data, $rateType),
            'weight_rules' => $this->resolveWeightRules($data, $rateType),
            'price_rules'  => $this->resolvePriceRules($data, $rateType),
            'free_above' => $this->decimal($data['free_above'] ?? null),
            'eta_min'    => $this->intOrNull($data['eta_min'] ?? null),
            'eta_max'    => $this->intOrNull($data['eta_max'] ?? null),
            'eta_unit'   => $this->resolveEtaUnit($data),
        ];
    }

    /**
     * Fields that live on the `shipping_zone_rates` child row. Shopify-style
     * "shipping option" — a single rate has its own rate_type, pricing, ETA,
     * and ordering. We don't fill `name` yet (single-rate phase); the zone
     * name doubles as the rate label in the UI.
     */
    protected function buildRatePayload(array $data, ShippingZone $zone): array
    {
        $rateType = $data['rate_type'] ?? RateTypeEnum::FLAT->value;

        return [
            'name'         => $data['rate_name'] ?? null,
            'rate_type'    => $rateType,
            'flat_rate'    => $this->resolveFlatRate($data, $rateType),
            'free_above'   => $this->decimal($data['free_above'] ?? null),
            'weight_rules' => $this->resolveWeightRules($data, $rateType),
            'price_rules'  => $this->resolvePriceRules($data, $rateType),
            'eta_min'      => $this->intOrNull($data['eta_min'] ?? null),
            'eta_max'      => $this->intOrNull($data['eta_max'] ?? null),
            'eta_unit'     => $this->resolveEtaUnit($data),
            'priority'     => $this->intOrNull($data['rate_priority'] ?? null) ?? 0,
            'is_active'    => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
    }

    /**
     * Replace the country coverage and serviceability rules in one transaction.
     */
    protected function syncCoverage(ShippingZone $zone, array $data): void
    {
        $countryIso2s = collect($data['country_iso2s'] ?? [])
            ->map(fn ($c) => strtoupper((string) $c))
            ->filter(fn ($c) => strlen($c) === 2)
            ->unique()
            ->values()
            ->all();
        $zone->countries()->sync($countryIso2s);

        ShippingZoneServiceabilityRule::where('shipping_zone_id', $zone->id)->delete();

        if ($this->resolveServiceabilityMode($data) === ServiceabilityModeEnum::NONE->value) {
            return;
        }

        $matchTypes = collect(ServiceabilityMatchTypeEnum::cases())->pluck('value')->all();
        $rangeType  = ServiceabilityMatchTypeEnum::PINCODE_RANGE->value;

        foreach ($data['serviceability'] ?? [] as $rule) {
            $iso2 = strtoupper((string) ($rule['country_iso2'] ?? ''));
            $type = (string) ($rule['match_type'] ?? '');
            $value = trim((string) ($rule['value'] ?? ''));
            if (strlen($iso2) !== 2 || !in_array($type, $matchTypes, true) || $value === '') {
                continue;
            }
            $valueTo = trim((string) ($rule['value_to'] ?? ''));
            if ($type === $rangeType && $valueTo === '') {
                continue;
            }
            ShippingZoneServiceabilityRule::create([
                'shipping_zone_id' => $zone->id,
                'country_iso2'     => $iso2,
                'match_type'       => $type,
                'value'            => Str::limit($value, 120, ''),
                'value_to'         => $type === $rangeType ? Str::limit($valueTo, 120, '') : null,
            ]);
        }
    }

    protected function resolveServiceabilityMode(array $data): string
    {
        $modes = collect(ServiceabilityModeEnum::cases())->pluck('value')->all();

        return in_array($data['serviceability_mode'] ?? null, $modes, true)
            ? $data['serviceability_mode']
            : ServiceabilityModeEnum::NONE->value;
    }


    protected function resolveFlatRate(array $data, string $rateType): ?float
    {
        return $rateType === RateTypeEnum::FLAT->value
            ? $this->decimal($data['flat_rate'] ?? null)
            : null;
    }

    protected function resolveWeightRules(array $data, string $rateType): ?array
    {
        return $rateType === RateTypeEnum::WEIGHT_BASED->value
            ? $this->sanitiseTiers($data['weight_rules'] ?? [], 'min_kg', 'max_kg')
            : null;
    }

    protected function resolvePriceRules(array $data, string $rateType): ?array
    {
        return $rateType === RateTypeEnum::PRICE_BASED->value
            ? $this->sanitiseTiers($data['price_rules'] ?? [], 'min_amount', 'max_amount')
            : null;
    }

    protected function resolveEtaUnit(array $data): string
    {
        return in_array($data['eta_unit'] ?? null, [EtaUnitEnum::HOURS->value, EtaUnitEnum::DAYS->value], true)
            ? $data['eta_unit']
            : EtaUnitEnum::DAYS->value;
    }

    /**
     * Filter tier rows to numeric bounds; drops any row missing required
     * keys so the router can scan `$tiers` without defensive checks.
     */
    protected function sanitiseTiers(array $rows, string $minKey, string $maxKey): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $rate = $row['rate'] ?? null;
            $min  = $row[$minKey] ?? null;
            $max  = $row[$maxKey] ?? null;
            if (!is_numeric($rate) || !is_numeric($min)) continue;
            $clean[] = [
                $minKey => (float) $min,
                $maxKey => is_numeric($max) ? (float) $max : null,
                'rate'  => (float) $rate,
            ];
        }
        return $clean;
    }

    protected function decimal($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    protected function intOrNull($value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
