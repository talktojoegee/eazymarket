@php
    use App\Enums\Shipping\FulfillmentMethodEnum;use App\Services\SettingService;$countries = $zone->countries ?? collect();
    $countriesLabel = $countries->count() > 0
        ? $countries->take(3)->pluck('name')->join(', ') . ($countries->count() > 3 ? ' +' . ($countries->count() - 3) : '')
        : __('labels.no_countries');

    $rate = $zone->relationLoaded('rates') ? $zone->rates->first() : $zone->defaultRate;

    $isShiprocketZone = $zone->method === FulfillmentMethodEnum::SHIPROCKET->value;
    $isManualZone = $zone->method === FulfillmentMethodEnum::MANUAL->value;
    $shippingConfig = app(SettingService::class)->shiprocketConfig();
    $shiprocketTitle = $shippingConfig['shiprocketTitle'] ?? 'Shiprocket';
    $manualTitle = $shippingConfig['manualTitle'] ?? __('labels.shipping_method_manual');
@endphp

<div class="list-group-item shipping-zone-row" data-zone-id="{{ $zone->id }}">
    <div class="row align-items-center">
        <div class="col">
            <div class="d-flex align-items-center gap-2 mb-1">
                <strong>{{ $zone->name }}</strong>
                @if (! $zone->is_active)
                    <span class="badge bg-secondary-lt">{{ __('labels.inactive') }}</span>
                @endif
                @if ($isShiprocketZone)
                    <span class="badge bg-blue-lt"><i
                            class="ti ti-truck-delivery me-1"></i>{{ $shiprocketTitle }}</span>
                @else
                    @if ($isManualZone)
                        <span class="badge bg-azure-lt"><i class="ti ti-truck me-1"></i>{{ $manualTitle }}</span>
                    @endif
                    @if ($rate)
                        <span class="badge bg-purple-lt">{{ __('labels.rate_type_' . $rate->rate_type) }}</span>
                    @endif
                    @if ($isManualZone && ($zone->serviceability_mode ?? 'none') !== 'none')
                        <span class="badge bg-orange-lt"><i class="ti ti-map-pin-off me-1"></i>{{ __('labels.serviceability_mode_' . $zone->serviceability_mode) }}</span>
                    @endif
                @endif
            </div>
            <div class="text-secondary small">
                <i class="ti ti-world me-1"></i>{{ $countriesLabel }}
                @if ($rate && ($rate->eta_min || $rate->eta_max))
                    · <i
                        class="ti ti-clock me-1"></i>{{ $rate->eta_min }}{{ $rate->eta_max && $rate->eta_max != $rate->eta_min ? '–' . $rate->eta_max : '' }} {{ __('labels.eta_unit_' . ($rate->eta_unit ?? 'days')) }}
                @endif
            </div>
        </div>
        <div class="col-auto d-flex gap-1">
            <button type="button"
                    class="btn btn-sm btn-outline-primary js-open-zone-modal"
                    data-mode="edit"
                    data-zone-id="{{ $zone->id }}">
                <i class="ti ti-pencil"></i>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-danger js-delete-zone"
                    data-zone-id="{{ $zone->id }}"
                    data-zone-name="{{ $zone->name }}">
                <i class="ti ti-trash"></i>
            </button>
        </div>
    </div>
</div>
