@php
    $namePrefix = $namePrefix ?? '';
    $loc = $loc ?? null;
    $googleApiKey = $googleApiKey ?? '';
    $n = fn ($f) => $namePrefix ? $namePrefix . '[' . $f . ']' : $f;
    $o = fn ($f) => $namePrefix ? $namePrefix . '.' . $f : $f;
@endphp

@if (!empty($googleApiKey))
    <div class="mb-3">
        <label class="form-label">{{ __('labels.pick_on_map') }}</label>
        <div id="location-map"
             data-google-api-key="{{ $googleApiKey }}"
             data-loading-text="{{ __('labels.map_fetching') }}"
             data-done-text="{{ __('labels.map_done') }}"
             data-error-text="{{ __('labels.map_error') }}"
             style="height:260px;border-radius:8px;overflow:hidden;"></div>
        <small class="form-hint"><span id="location-map-status">{{ __('labels.map_hint') }}</span></small>
    </div>
@endif

<div class="mb-3">
    <label class="form-label required">{{ __('labels.country') }}</label>
    <x-tom-select-country
        :name="$n('country_iso2')"
        :selected="old($o('country_iso2'), $loc->country_iso2 ?? '')"
        :required="true"
        placeholder="{{ __('labels.search_country') }}"
    />
</div>

<div class="mb-3">
    <label class="form-label required">{{ __('labels.address_1') }}</label>
    <input type="text" name="{{ $n('address_1') }}" id="loc-address-1" class="form-control"
           value="{{ old($o('address_1'), $loc->address_1 ?? '') }}" maxlength="255" required>
</div>
<div class="mb-3">
    <label class="form-label">{{ __('labels.address_2') }}</label>
    <input type="text" name="{{ $n('address_2') }}" id="loc-address-2" class="form-control"
           value="{{ old($o('address_2'), $loc->address_2 ?? '') }}" maxlength="255">
</div>
<div class="row g-2">
    <div class="col-md-6 mb-3">
        <label class="form-label required">{{ __('labels.city') }}</label>
        <input type="text" name="{{ $n('city') }}" id="loc-city" class="form-control"
               value="{{ old($o('city'), $loc->city ?? '') }}" maxlength="120" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label required">{{ __('labels.state') }}</label>
        <input type="text" name="{{ $n('state') }}" id="loc-state" class="form-control"
               value="{{ old($o('state'), $loc->state ?? '') }}" maxlength="120" required>
    </div>
</div>
<div class="row g-2">
    <div class="col-md-6 mb-3">
        <label class="form-label required">{{ __('labels.pin_code') }}</label>
        <input type="text" name="{{ $n('pin_code') }}" id="loc-pin-code" class="form-control"
               value="{{ old($o('pin_code'), $loc->pin_code ?? '') }}" maxlength="20" required>
    </div>
</div>
<div class="row g-2">
    <div class="col-md-6 mb-3">
        <label class="form-label">{{ __('labels.latitude') }}</label>
        <input type="number" step="any" name="{{ $n('latitude') }}" id="loc-latitude" class="form-control"
               value="{{ old($o('latitude'), $loc->latitude ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">{{ __('labels.longitude') }}</label>
        <input type="number" step="any" name="{{ $n('longitude') }}" id="loc-longitude" class="form-control"
               value="{{ old($o('longitude'), $loc->longitude ?? '') }}">
    </div>
</div>
<input type="hidden" name="{{ $n('country_name') }}" id="loc-country-name"
       value="{{ old($o('country_name'), $loc->country_name ?? '') }}">
