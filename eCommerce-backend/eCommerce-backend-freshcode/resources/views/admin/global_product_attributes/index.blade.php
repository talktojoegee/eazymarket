@php use App\Enums\Attribute\AttributeTypesEnum; @endphp
@extends('layouts.admin.app', [
    'page' => $menuAdmin['products']['active'] ?? '',
    'sub_page' => $menuAdmin['products']['route']['attributes']['sub_active'] ?? '',
])

@section('title', __('labels.attributes'))

@section('header_data')
    @php
        $page_title = __('labels.attributes');
        $page_pretitle = __('labels.admin').' '.__('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.products'), 'url' => route('admin.products.index')],
        ['title' => __('labels.attributes'), 'url' => ''],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.attributes') }}</h3>
                        <div class="text-secondary mt-1">{{ __('labels.attribute_management_help') }}</div>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="d-flex flex-column flex-lg-row gap-2">
                            <select class="form-select js-tom-select-remote" id="sellerFilter"
                                    data-endpoint="{{ url('admin/sellers/search') }}"
                                    data-placeholder="{{ __('labels.all_sellers') }}"
                                    data-preload="focus" data-preload-limit="20"
                                    aria-label="{{ __('labels.seller') }}">
                                <option value="">{{ __('labels.all_sellers') }}</option>
                            </select>
                            <select class="form-select text-capitalize" id="typeFilter" aria-label="{{ __('labels.filter_by_display_style') }}">
                                <option value="">{{ __('labels.all_display_styles') }}</option>
                                @foreach(AttributeTypesEnum::values() as $value)
                                    <option value="{{ $value }}">{{ __('labels.attribute_style_'.$value) }}</option>
                                @endforeach
                            </select>
                            @if($createPermission)
                                <a href="{{ route('admin.attributes.create') }}" class="btn btn-primary" id="create-attribute-link">
                                    <i class="ti ti-plus fs-3"></i>{{ __('labels.add_attribute') }}
                                </a>
                            @endif
                            <button type="button" class="btn btn-outline-primary" id="refresh">
                                <i class="ti ti-refresh fs-3"></i>{{ __('labels.refresh') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <x-datatable id="attributes-table" :columns="$columns"
                                 route="{{ route('admin.attributes.datatable') }}"
                                 :options="['order' => [[1, 'asc']], 'pageLength' => 10]"/>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.attributeManagementTranslations = {{ \Illuminate\Support\Js::from([
            'deleteAttribute' => __('labels.delete_attribute_confirmation'),
        ]) }};
    </script>
    <script src="{{ hyperAsset('assets/js/attribute.js') }}" defer></script>
@endpush
