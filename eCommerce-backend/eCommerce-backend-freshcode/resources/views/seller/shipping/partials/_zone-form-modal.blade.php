@php
    use App\Enums\Shipping\RateTypeEnum;
    use App\Enums\Shipping\EtaUnitEnum;
    use App\Enums\Shipping\FulfillmentMethodEnum;

    $currencySymbol = $systemSettings['currencySymbol'] ?? '';
    $shippingConfig = app(\App\Services\SettingService::class)->shiprocketConfig();
    $shiprocketEnabled = (bool) ($shippingConfig['shiprocketEnabled'] ?? false);
    $shiprocketTitle = $shippingConfig['shiprocketTitle'] ?? 'Shiprocket';
    $manualEnabled = (bool) ($shippingConfig['manualEnabled'] ?? true);
    $manualTitle = $shippingConfig['manualTitle'] ?? __('labels.shipping_method_manual');
    $zoneMethods = [];
    if ($manualEnabled) {
        $zoneMethods[] = FulfillmentMethodEnum::MANUAL->value;
    }
    if ($shiprocketEnabled) {
        $zoneMethods[] = FulfillmentMethodEnum::SHIPROCKET->value;
    }
    $methodLabels = [
        FulfillmentMethodEnum::MANUAL->value => $manualTitle,
        FulfillmentMethodEnum::SHIPROCKET->value => $shiprocketTitle,
    ];
@endphp

<div class="modal modal-blur fade" id="shippingZoneModal" tabindex="-1" role="dialog" aria-hidden="true" data-profile-id="{{ $profile->id }}" data-currency-symbol="{{ $currencySymbol }}">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shippingZoneModalTitle">{{ __('labels.add_shipping_zone') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="shippingZoneForm" data-mode="create" data-zone-id="">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label required">{{ __('labels.zone_name') }}</label>
                            <input type="text" name="name" class="form-control" maxlength="64" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.priority') }}</label>
                            <input type="number" name="priority" class="form-control" min="0" max="1000" value="0">
                            <small class="form-hint">{{ __('labels.priority_hint') }}</small>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <label class="form-check form-switch mb-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                                <span class="form-check-label">{{ __('labels.is_active') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">{{ __('labels.shipping_method') }}</label>
                            <select name="method" class="form-select js-zone-method"
                                    data-shiprocket-rate="{{ RateTypeEnum::CARRIER_QUOTED->value }}"
                                    data-manual-rates="{{ implode(',', [RateTypeEnum::FLAT->value, RateTypeEnum::WEIGHT_BASED->value, RateTypeEnum::PRICE_BASED->value]) }}">
                                @foreach ($zoneMethods as $m)
                                    <option value="{{ $m }}">{{ $methodLabels[$m] ?? $m }}</option>
                                @endforeach
                            </select>
                            <small class="form-hint">{{ __('labels.shipping_method_hint', ['title' => $shiprocketTitle]) }}</small>
                        </div>
                        <div class="col-md-6" id="zoneRateTypeCol">
                            <label class="form-label required">{{ __('labels.rate_type') }}</label>
                            <select name="rate_type" class="form-select js-zone-rate-type">
                                @foreach (RateTypeEnum::cases() as $r)
                                    <option value="{{ $r->value }}" data-methods="{{ $r->value === RateTypeEnum::CARRIER_QUOTED->value ? FulfillmentMethodEnum::SHIPROCKET->value : FulfillmentMethodEnum::MANUAL->value }}">{{ __('labels.rate_type_' . $r->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex d-none" id="zoneShiprocketNote">
                        <i class="ti ti-info-circle me-2 mt-1"></i>
                        <div>{{ __('labels.shiprocket_zone_note', ['title' => $shiprocketTitle]) }}</div>
                    </div>

                    <div class="mb-3" data-show-when-rate="{{ RateTypeEnum::FLAT->value }}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.flat_rate') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" min="0" name="flat_rate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('labels.free_above') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" min="0" name="free_above" class="form-control">
                                </div>
                                <small class="form-hint">{{ __('labels.free_above_hint') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" data-show-when-rate="{{ RateTypeEnum::WEIGHT_BASED->value }}" hidden>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ __('labels.weight_tiers') }}</label>
                            <button type="button" class="btn btn-sm btn-link js-add-weight-tier">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_tier') }}
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-vcenter">
                                <thead>
                                    <tr>
                                        <th>{{ __('labels.min_kg') }}</th>
                                        <th>{{ __('labels.max_kg') }}</th>
                                        <th>{{ __('labels.rate') }}</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody id="weight-tier-rows"></tbody>
                            </table>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('labels.free_above') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" min="0" name="free_above_weight" class="form-control js-mirror-free-above">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" data-show-when-rate="{{ RateTypeEnum::PRICE_BASED->value }}" hidden>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ __('labels.price_tiers') }}</label>
                            <button type="button" class="btn btn-sm btn-link js-add-price-tier">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_tier') }}
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-vcenter">
                                <thead>
                                    <tr>
                                        <th>{{ __('labels.min_amount') }}</th>
                                        <th>{{ __('labels.max_amount') }}</th>
                                        <th>{{ __('labels.rate') }}</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody id="price-tier-rows"></tbody>
                            </table>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('labels.free_above') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" min="0" name="free_above_price" class="form-control js-mirror-free-above">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" data-show-when-rate="{{ RateTypeEnum::CARRIER_QUOTED->value }}" hidden>
                        <p class="text-secondary small mb-0">
                            <i class="ti ti-info-circle me-1"></i>{{ __('labels.carrier_quoted_hint') }}
                        </p>
                    </div>

                    <div class="row g-3 mb-3" id="zoneEtaRow">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.eta_min') }}</label>
                            <input type="number" min="0" name="eta_min" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.eta_max') }}</label>
                            <input type="number" min="0" name="eta_max" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.eta_unit') }}</label>
                            <select name="eta_unit" class="form-select">
                                @foreach (EtaUnitEnum::cases() as $u)
                                    <option value="{{ $u->value }}">{{ __('labels.eta_unit_' . $u->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>


                    <hr class="my-3">
                    <h4 class="mb-2">{{ __('labels.zone_coverage') }}</h4>
                    <p class="text-secondary small mb-3">{{ __('labels.zone_coverage_country_only_hint') }}</p>

                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.countries') }}</label>
                        <select name="country_iso2s[]"
                                id="zone-countries"
                                class="form-select"
                                data-placeholder="{{ __('labels.select_countries') }}"
                                data-allowed-countries='@json($zoneCountries ?? [])'
                                multiple></select>
                    </div>

                    <div id="zoneServiceability">
                        <hr class="my-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="ti ti-map-pin-off"></i>
                            <h4 class="mb-0">{{ __('labels.serviceability') }}</h4>
                        </div>
                        <p class="text-secondary small mb-3">{{ __('labels.serviceability_hint') }}</p>

                        <input type="hidden" name="serviceability_mode" id="serviceability-mode" value="none">
                        <div class="btn-group w-100 mb-2" role="group" id="serviceability-mode-group">
                            @foreach (['none', 'deny', 'allow'] as $m)
                                <button type="button"
                                        class="btn btn-outline-primary js-serviceability-mode {{ $m === 'none' ? 'active' : '' }}"
                                        data-mode="{{ $m }}">
                                    {{ __('labels.serviceability_mode_' . $m) }}
                                </button>
                            @endforeach
                        </div>
                        <p class="text-secondary small mb-3" id="serviceability-mode-hint">{{ __('labels.serviceability_mode_none_hint') }}</p>

                        <div id="serviceability-rules-wrap" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">{{ __('labels.serviceability_rules') }}</label>
                                <button type="button" class="btn btn-sm btn-link js-add-serviceability-rule">
                                    <i class="ti ti-plus me-1"></i>{{ __('labels.add_rule') }}
                                </button>
                            </div>
                            <div id="serviceability-rows"></div>
                            <p class="text-secondary small mb-0">
                                <i class="ti ti-info-circle me-1"></i>{{ __('labels.serviceability_country_scope_hint') }}
                            </p>
                        </div>
                    </div>

                    </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="shippingZoneSubmit">
                    {{ __('labels.save_zone') }}
                </button>
            </div>
        </div>
    </div>
</div>
