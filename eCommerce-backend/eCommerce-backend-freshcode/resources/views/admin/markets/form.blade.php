@php use App\Models\Currency; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['markets']['active'] ?? '', 'sub_page' => $menuAdmin['markets']['route']['index']['sub_active'] ?? ''])

@php
    $isEdit = isset($market) && $market;
@endphp

@section('title', $isEdit ? __('labels.edit_market') : __('labels.add_market'))

@section('header_data')
    @php
        $page_title = $isEdit ? __('labels.edit_market') : __('labels.add_market');
        $page_pretitle = $isEdit ? __('labels.update_details') : __('labels.create_new');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.markets'), 'url' => route('admin.markets.index')],
        ['title' => $isEdit ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ $isEdit ? __('labels.edit_market') : __('labels.add_market') }}
                    </h3>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>

                <form
                    action="{{ $isEdit ? route('admin.markets.update', $market->id) : route('admin.markets.store') }}" method="POST"
                      class="card-body form-submit">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">{{ __('labels.code') }}</label>
                            <input name="code" type="text"
                                   class="form-control" maxlength="8"
                                   placeholder="GLOBAL, IN, US..."
                                   value="{{ old('code', $market->code ?? '') }}" required>
                            <small class="text-muted">{{ __('labels.market_code_hint') }}</small>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label required">{{ __('labels.name') }}</label>
                            <input name="name" type="text"
                                   class="form-control" maxlength="64"
                                   value="{{ old('name', $market->name ?? '') }}" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label required">{{ __('labels.currency') }}</label>
                            <x-tom-select-currency
                                name="currency_code"
                                :selected="old('currency_code', $market->currency_code ?? 'USD')"
                                required
                            />
                        </div>

                        <div class="col-md-2">
                            <label class="form-label required">{{ __('labels.default_language') }}</label>
                            <select name="default_language" class="form-select" required>
                                @foreach($languages as $language)
                                    <option value="{{ $language->code }}" @selected(old('default_language', $market->default_language ?? 'en') === $language->code)>{{ $language->native_name }} ({{ $language->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.status') }}</label>
                            <select name="status" class="form-select">
                                @foreach($statuses as $s)
                                    <option value="{{ $s }}"
                                        @selected(old('status', $market->status ?? 'active') === $s)>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

<div class="col-12">
                            <label class="form-label required">{{ __('labels.countries') }}</label>
                            <x-tom-select-country
                                name="country_iso2"
                                multiple
                                required
                                :selected="old('country_iso2', $selected ?? [])"
                            />
                            <small class="text-muted">{{ __('labels.market_countries_hint') }}</small>
                        </div>
                    </div>

                    <div class="card mt-4" id="market-number-format-card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.number_format_card_title') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.symbol_position') }}</label>
                                    <select name="symbol_position" class="form-select" data-mnf="symbol_position">
                                        @php $sp = old('symbol_position', $market->symbol_position ?? 'before'); @endphp
                                        <option
                                            value="before" @selected($sp === 'before')>{{ __('labels.symbol_before') }}</option>
                                        <option
                                            value="after" @selected($sp === 'after')>{{ __('labels.symbol_after') }}</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label d-block">{{ __('labels.space_between_symbol') }}</label>
                                    @php $sbs = (bool) old('space_between_symbol', $market->space_between_symbol ?? false); @endphp
                                    <input type="hidden" name="space_between_symbol" value="0">
                                    <label class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="space_between_symbol"
                                               value="1" data-mnf="space_between_symbol" @checked($sbs)>
                                        <span
                                            class="form-check-label">{{ __('labels.space_between_symbol_hint') }}</span>
                                    </label>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.thousand_separator') }}</label>
                                    <input type="text" name="thousand_separator" class="form-control"
                                           maxlength="3" data-mnf="thousand_separator"
                                           placeholder=","
                                           value="{{ old('thousand_separator', $market->thousand_separator ?? ',') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.decimal_separator') }}</label>
                                    <input type="text" name="decimal_separator" class="form-control"
                                           maxlength="3" data-mnf="decimal_separator"
                                           placeholder="."
                                           value="{{ old('decimal_separator', $market->decimal_separator ?? '.') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.grouping_style') }}</label>
                                    <select name="grouping_style" class="form-select" data-mnf="grouping_style">
                                        @php $gs = old('grouping_style', $market->grouping_style ?? 'western'); @endphp
                                        <option
                                            value="western" @selected($gs === 'western')>{{ __('labels.grouping_western') }}</option>
                                        <option
                                            value="indian" @selected($gs === 'indian')>{{ __('labels.grouping_indian') }}</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.decimal_places') }}</label>
                                    <input type="number" name="decimal_places" class="form-control"
                                           min="0" max="4" data-mnf="decimal_places"
                                           placeholder="{{ __('labels.use_currency_default') }}"
                                           value="{{ old('decimal_places', $market->decimal_places ?? '') }}">
                                    <small class="text-muted">{{ __('labels.decimal_places_hint') }}</small>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">{{ __('labels.negative_format') }}</label>
                                    <select name="negative_format" class="form-select" data-mnf="negative_format">
                                        @php $nf = old('negative_format', $market->negative_format ?? '-{n}'); @endphp
                                        <option value="-{n}" @selected($nf === '-{n}')>-{n}
                                            ({{ __('labels.negative_minus_prefix') }})
                                        </option>
                                        <option value="({n})" @selected($nf === '({n})')>({n})
                                            ({{ __('labels.negative_parens') }})
                                        </option>
                                        <option value="{n}-" @selected($nf === '{n}-')>{n}-
                                            ({{ __('labels.negative_minus_suffix') }})
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="w-100 p-2 border rounded text-center">
                                        <small class="text-muted d-block">{{ __('labels.preview') }}</small>
                                        <span id="market-number-format-preview" class="fw-semibold fs-3"
                                              data-currency-symbol="{{ optional(Currency::query()->where('code', old('currency_code', $market->currency_code ?? 'USD'))->first())->symbol ?? '$' }}">
                                            —
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4" id="market-fees-card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.market_fees_card_title') }}</h3>
                        </div>
                        <div class="card-body">
                            @php
                                $baseCurrencySymbol = optional(Currency::query()->where('is_base', true)->first())->symbol ?? '$';
                            @endphp
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('labels.platform_fee') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ $baseCurrencySymbol }}</span>
                                        <input type="number" name="platform_fee" class="form-control"
                                               min="0" step="0.01"
                                               value="{{ old('platform_fee', $market->platform_fee ?? '0.00') }}">
                                    </div>
                                    <small class="text-muted">{{ __('labels.platform_fee_hint') }}</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">{{ __('labels.cod_fee') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ $baseCurrencySymbol }}</span>
                                        <input type="number" name="cod_fee" class="form-control"
                                               min="0" step="0.01"
                                               value="{{ old('cod_fee', $market->cod_fee ?? '0.00') }}">
                                    </div>
                                    <small class="text-muted">{{ __('labels.cod_fee_hint') }}</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">{{ __('labels.max_cod_amount') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ $baseCurrencySymbol }}</span>
                                        <input type="number" name="max_cod_amount" class="form-control"
                                               min="0" step="0.01"
                                               value="{{ old('max_cod_amount', $market->max_cod_amount ?? '0.00') }}">
                                    </div>
                                    <small class="text-muted">{{ __('labels.max_cod_amount_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-footer mt-4">
                        <a href="{{ route('admin.markets.index') }}" class="btn">
                            {{ __('labels.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            {{ $isEdit ? __('labels.update') : __('labels.create') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-tax align-text-bottom me-1">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M5 21v-16a2 2 0 0 1 2 -2h7l5 5v13l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2"/>
                                <path d="M9 7v-1h6"/>
                                <path d="M15 11v-1h-6"/>
                                <path d="M9 15v-1h6"/>
                            </svg>
                            {{ __('labels.taxes_and_duties') }}
                        </h3>
                    </div>
                </div>
                <div class="card-body">
                    @if(! $isEdit)
                        <p class="text-muted mb-0">
                            {{ __('labels.market_tax_save_first_hint') }}
                        </p>
                    @elseif(($taxClasses ?? collect())->isEmpty())
                        <div class="empty py-3">
                            <p class="empty-title mb-1">{{ __('labels.market_no_tax_classes') }}</p>
                            <p class="empty-subtitle text-muted mb-3">
                                {{ __('labels.market_no_tax_classes_hint') }}
                            </p>
                            <a href="{{ route('admin.tax-rates.index') }}?market_id={{ $market->id }}&open=create-tax-class"
                               class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                    <path d="M12 5l0 14"/>
                                    <path d="M5 12l14 0"/>
                                </svg>
                                {{ __('labels.add_tax_group') }}
                            </a>
                        </div>
                    @else
                        <p class="text-muted mb-2">
                            {{ trans_choice('labels.market_has_n_tax_classes', $taxClasses->count(), ['count' => $taxClasses->count()]) }}
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach($market->taxClasses as $tc)
                                <div class="card bg-blue-lt p-2">
                                    <div class="fw-bold">{{ $tc->title }}</div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach($tc->taxRates as $rate)
                                            <span class="badge bg-blue-lt">{{ $rate->title }}: {{ $rate->rate }}%</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('admin.tax-rates.index') }}?market_id={{ $market->id }}"
                           class="btn btn-outline-primary btn-sm">
                            {{ __('labels.manage_tax_classes') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ hyperAsset('assets/js/market.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/admin-market-format-preview.js') }}"></script>
@endpush
