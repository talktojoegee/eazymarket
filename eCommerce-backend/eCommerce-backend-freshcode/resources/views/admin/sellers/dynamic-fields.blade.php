@extends('layouts.admin.app', ['page' => $menuAdmin['seller_management']['active'] ?? '', 'sub_page' => $menuAdmin['seller_management']['route']['seller_form_fields']['sub_active'] ?? ''])

@section('title', __('labels.seller_form_fields'))

@section('header_data')
    @php
        $page_title = __('labels.seller_form_fields');
        $page_pretitle = __('labels.seller_management');
        $breadcrumbs = [
            ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
            ['title' => __('labels.sellers'), 'url' => route('admin.sellers.index')],
            ['title' => $page_title, 'url' => null],
        ];
    @endphp
@endsection

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-pretitle">{{ __('labels.seller_management') }}</div>
                        <h3 class="card-title">{{ $page_title }}</h3>
                    </div>
                    <div class="card-actions"><x-breadcrumb :items="$breadcrumbs" /></div>
                </div>
                <div class="card-body">
                    <p class="text-secondary">{{ __('Choose one or more countries to view or configure the Bank Details and Business Documents fields sellers see in those countries.') }}</p>
                    <form action="{{ route('admin.sellers.dynamic-fields.index') }}" method="get" class="row align-items-end" id="dynamic-seller-field-country-filter">
                        <input type="hidden" name="section" value="{{ $section }}">
                        <div class="col-lg-9 mb-3">
                            <label class="form-label" for="field-country-filter">{{ __('Countries to manage') }}</label>
                            <select id="field-country-filter" name="country_codes[]" class="form-select js-tom-select-country" data-endpoint="{{ url('/countries') }}" data-value-field="iso2" data-text-field="name" data-placeholder="{{ __('Search and select countries') }}" data-selected='@json($selectedCountries)' multiple></select>
                        </div>
                        <div class="col-lg-3 mb-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('Load countries') }}</button>
                            <button type="button" class="btn btn-outline-secondary" id="clear-field-country-filter">{{ __('Clear') }}</button>
                        </div>
                    </form>
                    <ul class="nav nav-tabs mt-3" role="tablist">
                        <li class="nav-item" role="presentation"><a class="nav-link {{ $section === 'bank_details' ? 'active' : '' }}" href="{{ route('admin.sellers.dynamic-fields.index', ['section' => 'bank_details', 'country_codes' => $selectedCountries->all()]) }}">{{ __('labels.bank_details') }}</a></li>
                        <li class="nav-item" role="presentation"><a class="nav-link {{ $section === 'business_documents' ? 'active' : '' }}" href="{{ route('admin.sellers.dynamic-fields.index', ['section' => 'business_documents', 'country_codes' => $selectedCountries->all()]) }}">{{ __('labels.business_documents') }}</a></li>
                    </ul>
                </div>
                @if($canManageFields)
                    <div class="card-body border-top">
                        <h4 class="card-title mb-3">{{ $editingField ? __('Edit field') : __('Add field') }}</h4>
                        <form action="{{ $editingField ? route('admin.sellers.dynamic-fields.update', $editingField) : route('admin.sellers.dynamic-fields.store') }}" method="post" class="form-submit" novalidate>
                            @csrf
                            @if($editingField)
                                @method('PUT')
                            @endif
                            <input type="hidden" name="section" value="{{ $section }}">
                            <div class="row align-items-end">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="field-label">{{ __('Field label') }}</label>
                                    <input id="field-label" name="label" class="form-control" maxlength="120" value="{{ old('label', $editingField?->label ?? '') }}" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label" for="field-type">{{ __('Input type') }}</label>
                                    <select id="field-type" name="type" class="form-select" @disabled($editingField) required>
                                        @foreach(['select', 'number', 'text', 'image'] as $type)
                                            <option value="{{ $type }}" @selected(old('type', $editingField?->type ?? 'select') === $type)>{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                    @if($editingField)
                                        <input type="hidden" name="type" value="{{ $editingField->type }}">
                                        {{-- <div class="form-hint">{{ __('Input type cannot be changed after the field is created.') }}</div> --}}
                                    @endif
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label" for="field-placeholder">{{ __('Placeholder') }} <span class="text-secondary">({{ __('optional') }})</span></label>
                                    <input id="field-placeholder" name="placeholder" class="form-control" maxlength="160" value="{{ old('placeholder', $editingField?->placeholder ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3" id="field-default-wrap">
                                    <label class="form-label" for="field-default-value">{{ __('Default value') }} <span class="text-secondary">({{ __('optional') }})</span></label>
                                    <input id="field-default-value" name="default_value" class="form-control" maxlength="1000" value="{{ old('default_value', $editingField?->default_value ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3" id="field-options-wrap" hidden>
                                    <label class="form-label" for="field-options">{{ __('Dropdown options') }}</label>
                                    <div class="form-hint mb-2">{{ __('Enter one option per line.') }}</div>
                                    <textarea id="field-options" name="options" class="form-control" rows="2">{{ old('options', $fieldOptionsText) }}</textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="field-country-target">{{ __('Countries this field applies to') }}</label>
                                    <select id="field-country-target" name="country_codes[]" class="form-select js-tom-select-country" data-endpoint="{{ url('/countries') }}" data-value-field="iso2" data-text-field="name" data-placeholder="{{ __('Search and select countries') }}" data-selected='@json(old('country_codes', $fieldCountryCodes))' data-editing="{{ $editingField ? '1' : '0' }}" multiple required></select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="required" value="1" @checked(old('required', $editingField?->required ?? false))>
                                        <span class="form-check-label">{{ __('Required') }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                @if($editingField)
                                    <a class="btn btn-outline-secondary" href="{{ route('admin.sellers.dynamic-fields.index', ['section' => $section, 'country_codes' => $selectedCountries->all()]) }}">{{ __('labels.cancel') }}</a>
                                @else
                                    <button class="btn btn-outline-secondary" type="reset">{{ __('labels.cancel') }}</button>
                                @endif
                                <button class="btn btn-primary" type="submit"><i class="ti {{ $editingField ? 'ti-device-floppy' : 'ti-plus' }}"></i> {{ $editingField ? __('Save changes') : __('Add field') }}</button>
                            </div>
                        </form>
                    </div>
                @endif
                <div class="card-table border-top">
                    <x-datatable id="dynamic-seller-fields-table" :columns="$columns" :route="route('admin.sellers.dynamic-fields.datatable', ['section' => $section, 'country_codes' => $selectedCountries->all()])" :options="['pageLength' => 10, 'order' => [[0, 'asc']]]" />
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/dynamic-seller-fields.js') }}"></script>
@endpush
