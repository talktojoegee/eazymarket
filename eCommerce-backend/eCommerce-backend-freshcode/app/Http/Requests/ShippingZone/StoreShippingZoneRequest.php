<?php

namespace App\Http\Requests\ShippingZone;

use App\Enums\Shipping\EtaUnitEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\RateTypeEnum;
use App\Enums\Shipping\ServiceabilityMatchTypeEnum;
use App\Enums\Shipping\ServiceabilityModeEnum;
use App\Services\SettingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a new shipping zone under a profile.
 *
 * Rate-type-specific fields are required_if-guarded so the seller can't save
 * a flat zone with a null rate, or a weight-based zone without tiers. The
 * service also coerces them defensively — validation is the friendlier of
 * the two checkpoints (it surfaces inline messages).
 *
 * Name uniqueness is scoped per-profile (two profiles can both have a
 * "Domestic" zone — they're independent).
 */
class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $profileId = (int) $this->route('profileId');
        $rateTypes  = collect(RateTypeEnum::cases())->pluck('value')->all();
        $methods    = collect(FulfillmentMethodEnum::cases())->pluck('value')->all();
        $etaUnits   = collect(EtaUnitEnum::cases())->pluck('value')->all();
        $svcModes   = collect(ServiceabilityModeEnum::cases())->pluck('value')->all();
        $svcTypes   = collect(ServiceabilityMatchTypeEnum::cases())->pluck('value')->all();
        $rangeType  = ServiceabilityMatchTypeEnum::PINCODE_RANGE->value;

        $countryRule = ['string', 'size:2'];
        $allowedCountries = $this->allowedCountryIso2s($profileId);
        if (!empty($allowedCountries)) {
            $countryRule[] = Rule::in($allowedCountries);
        }

        return [
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('shipping_zones', 'name')
                    ->where(fn ($q) => $q->where('shipping_profile_id', $profileId)->whereNull('deleted_at')),
            ],
            'method'    => ['required', Rule::in($methods)],
            'rate_type' => ['required', Rule::in($rateTypes)],

            'flat_rate'    => ['nullable', 'numeric', 'min:0', 'required_if:rate_type,' . RateTypeEnum::FLAT->value],
            'free_above'   => ['nullable', 'numeric', 'min:0'],

            'weight_rules'           => ['nullable', 'array', 'required_if:rate_type,' . RateTypeEnum::WEIGHT_BASED->value],
            'weight_rules.*.min_kg'  => ['required_with:weight_rules', 'numeric', 'min:0'],
            'weight_rules.*.max_kg'  => ['nullable', 'numeric', 'gt:weight_rules.*.min_kg'],
            'weight_rules.*.rate'    => ['required_with:weight_rules', 'numeric', 'min:0'],

            'price_rules'                => ['nullable', 'array', 'required_if:rate_type,' . RateTypeEnum::PRICE_BASED->value],
            'price_rules.*.min_amount'   => ['required_with:price_rules', 'numeric', 'min:0'],
            'price_rules.*.max_amount'   => ['nullable', 'numeric', 'gt:price_rules.*.min_amount'],
            'price_rules.*.rate'         => ['required_with:price_rules', 'numeric', 'min:0'],

            'eta_min'  => ['nullable', 'integer', 'min:0'],
            'eta_max'  => ['nullable', 'integer', 'gte:eta_min'],
            'eta_unit' => ['nullable', Rule::in($etaUnits)],

            'operating_hours'     => ['nullable', 'array'],

            'priority'  => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],

            'country_iso2s'           => ['nullable', 'array'],
            'country_iso2s.*'         => $countryRule,

            'serviceability_mode'        => ['nullable', Rule::in($svcModes)],
            'serviceability'             => ['nullable', 'array'],
            'serviceability.*.country_iso2' => ['required_with:serviceability', 'string', 'size:2'],
            'serviceability.*.match_type'   => ['required_with:serviceability', Rule::in($svcTypes)],
            'serviceability.*.value'        => ['required_with:serviceability', 'string', 'max:120'],
            'serviceability.*.value_to'     => ['nullable', 'string', 'max:120', 'required_if:serviceability.*.match_type,' . $rangeType],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $method   = $this->input('method');
            $rateType = $this->input('rate_type');
            $shiprocket = FulfillmentMethodEnum::SHIPROCKET->value;
            $carrierQuoted = RateTypeEnum::CARRIER_QUOTED->value;

            if ($method === $shiprocket && $rateType !== $carrierQuoted) {
                $validator->errors()->add('rate_type', __('labels.shipping_zone_shiprocket_requires_carrier_quoted'));
            }
            if ($method !== $shiprocket && $rateType === $carrierQuoted) {
                $validator->errors()->add('rate_type', __('labels.shipping_zone_carrier_quoted_shiprocket_only'));
            }
            $shippingConfig = app(SettingService::class)->shiprocketConfig();
            $shiprocketEnabled = (bool) ($shippingConfig['shiprocketEnabled'] ?? false);
            if ($method === $shiprocket && !$shiprocketEnabled) {
                $validator->errors()->add('method', __('labels.shiprocket_disabled'));
            }
            $manualEnabled = (bool) ($shippingConfig['manualEnabled'] ?? true);
            if ($method === FulfillmentMethodEnum::MANUAL->value && !$manualEnabled) {
                $validator->errors()->add('method', __('labels.manual_shipping_disabled'));
            }

            $this->validateServiceabilityGeo($validator);
            $this->validateTierRanges($validator, 'weight_rules', 'min_kg', 'max_kg');
            $this->validateTierRanges($validator, 'price_rules', 'min_amount', 'max_amount');
        });
    }

    /**
     * Reject tiers that overlap each other. Overlapping tiers make the selected
     * rate depend on array order, which is not something the seller controls.
     */
    protected function validateTierRanges($validator, string $field, string $minKey, string $maxKey): void
    {
        $rules = $this->input($field);
        if (!is_array($rules) || count($rules) < 2) {
            return;
        }

        $ranges = [];
        foreach ($rules as $i => $rule) {
            if (!is_array($rule) || !isset($rule[$minKey]) || !is_numeric($rule[$minKey])) {
                continue;
            }
            $ranges[] = [
                'i' => $i,
                'min' => (float) $rule[$minKey],
                'max' => isset($rule[$maxKey]) && is_numeric($rule[$maxKey]) ? (float) $rule[$maxKey] : INF,
            ];
        }

        foreach ($ranges as $a) {
            foreach ($ranges as $b) {
                if ($a['i'] >= $b['i']) {
                    continue;
                }
                if ($a['min'] <= $b['max'] && $b['min'] <= $a['max']) {
                    $validator->errors()->add(
                        "$field.{$b['i']}.$minKey",
                        __('labels.shipping_tiers_overlap')
                    );
                }
            }
        }
    }

    /**
     * Reject city / state serviceability values that don't exist in the local
     * geo tables — the picker only offers real names, so a non-matching value
     * means a tampered or stale payload. Pincode rules are skipped.
     */
    protected function validateServiceabilityGeo($validator): void
    {
        $state = ServiceabilityMatchTypeEnum::STATE->value;
        $city  = ServiceabilityMatchTypeEnum::CITY->value;

        foreach ((array) $this->input('serviceability', []) as $i => $rule) {
            $type    = $rule['match_type'] ?? null;
            $value   = trim((string) ($rule['value'] ?? ''));
            $country = strtoupper((string) ($rule['country_iso2'] ?? ''));
            if (!in_array($type, [$state, $city], true) || $value === '' || strlen($country) !== 2) {
                continue;
            }

            $exists = $type === $state
                ? \App\Models\GeoState::where('country_iso2', $country)->where('name', $value)->exists()
                : \App\Models\GeoCity::where('country_iso2', $country)->where('name', $value)->exists();

            if (!$exists) {
                $validator->errors()->add("serviceability.$i.value", __('labels.serviceability_value_not_real'));
            }
        }
    }

    public function messages(): array
    {
        return [
            'name.unique'              => __('labels.shipping_zone_name_unique'),
            'flat_rate.required_if'    => __('labels.shipping_zone_flat_rate_required'),
            'weight_rules.required_if' => __('labels.shipping_zone_weight_tiers_required'),
            'price_rules.required_if'  => __('labels.shipping_zone_price_tiers_required'),
            'country_iso2s.*.in'       => __('labels.shipping_zone_country_not_in_store'),
        ];
    }

    /**
     * ISO2 codes the profile's store may ship to (union of its markets'
     * countries). Empty when the store has no markets — callers then skip the
     * whitelist so the seller isn't locked out of adding any country.
     *
     * @return array<int, string>
     */
    protected function allowedCountryIso2s(int $profileId): array
    {
        $profile = \App\Models\ShippingProfile::with('store')->find($profileId);

        return $profile?->store?->allowedShippingCountries()->pluck('iso2')->all() ?? [];
    }
}
