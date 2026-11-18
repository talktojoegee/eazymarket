@php
    use App\Enums\Attribute\AttributeTypesEnum;

    $isEdit = ! empty($attribute);
    $formTitle = $isEdit ? __('labels.edit_attribute') : __('labels.add_attribute');
    $action = $isEdit
        ? route('seller.attributes.update', $attribute->id)
        : route('seller.attributes.store');
    $initialData = $isEdit ? $attribute : null;
@endphp
@extends('layouts.seller.app', ['page' => $menuSeller['attributes']['active'] ?? ''])

@section('title', $formTitle)

@section('header_data')
    @php
        $page_title = $formTitle;
        $page_pretitle = __('labels.seller').' '.__('labels.attributes');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.attributes'), 'url' => route('seller.attributes.index')],
        ['title' => $formTitle, 'url' => ''],
    ];
@endphp

@section('seller-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ $formTitle }}</h3>
                        <div class="text-secondary mt-1">{{ __('labels.attribute_form_help') }}</div>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    @if($isEdit)
                        <div class="card-actions">
                            <span class="badge bg-blue-lt">
                                {{ trans_choice('labels.products_using_count', $attribute->products_count, ['count' => $attribute->products_count]) }}
                            </span>
                        </div>
                    @endif
                </div>

                <form class="form-submit" id="attribute-form" action="{{ $action }}" method="POST"
                      enctype="multipart/form-data" data-redirect="{{ route('seller.attributes.index') }}">
                    @csrf
                    <div class="card-body border-bottom">
                        <div class="mb-4">
                            <label class="form-label required" for="attribute-title">{{ __('labels.attribute_name') }}</label>
                            <input type="text" id="attribute-title" name="title" class="form-control"
                                   maxlength="191" placeholder="{{ __('labels.attribute_name_example') }}"
                                   value="{{ old('title', $attribute->title ?? '') }}" required>
                            <div class="form-hint">{{ __('labels.attribute_name_help') }}</div>
                        </div>

                        <fieldset id="attribute-style-fieldset">
                            <legend class="form-label required">{{ __('labels.display_style') }}</legend>
                            <div class="row g-3">
                                @foreach($attributeTypes as $type)
                                    @php $selectedType = old('swatche_type', $attribute->swatche_type ?? AttributeTypesEnum::TEXT()); @endphp
                                    <div class="col-12 col-md-4">
                                        <label class="form-selectgroup-item w-100">
                                            <input type="radio" name="swatche_type" value="{{ $type }}"
                                                   class="form-selectgroup-input" @checked($selectedType === $type)>
                                            <span class="form-selectgroup-label d-flex align-items-start gap-3 h-100">
                                                <i class="ti {{ $type === 'text' ? 'ti-forms' : ($type === 'color' ? 'ti-palette' : 'ti-photo') }} fs-2 text-primary"></i>
                                                <span>
                                                    <span class="d-block fw-semibold">{{ __('labels.attribute_style_'.$type) }}</span>
                                                    <span class="d-block text-secondary small">{{ __('labels.attribute_style_'.$type.'_help') }}</span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-hint d-none" id="attribute-style-locked-hint">
                                <i class="ti ti-lock me-1"></i>{{ __('labels.attribute_display_style_locked') }}
                            </div>
                        </fieldset>
                    </div>

                    <div class="card-body">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                            <div>
                                <h4 class="card-title mb-1">{{ __('labels.attribute_values') }}</h4>
                                <div class="text-secondary">{{ __('labels.attribute_values_help') }}</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="add-attribute-value">
                                <i class="ti ti-plus"></i>{{ __('labels.add_value') }}
                            </button>
                        </div>
                        <div id="attribute-values" class="d-flex flex-column gap-3"></div>
                        <div id="removed-attribute-values"></div>
                    </div>

                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="{{ route('seller.attributes.index') }}" class="btn">{{ __('labels.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy"></i>
                            {{ $isEdit ? __('labels.update_attribute') : __('labels.save_attribute') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $attributeManagementTranslations = [
            'valueName' => __('labels.value_name'),
            'valueExample' => __('labels.attribute_value_example'),
            'color' => __('labels.color'),
            'image' => __('labels.image'),
            'replaceImage' => __('labels.replace_image'),
            'remove' => __('labels.remove'),
            'usedByProducts' => __('labels.value_used_by_products'),
            'deleteAttribute' => __('labels.delete_attribute_confirmation'),
        ];
    @endphp
    <script>
        window.attributeInitialData = {{ \Illuminate\Support\Js::from($initialData) }};
        window.attributeManagementTranslations = {{ \Illuminate\Support\Js::from($attributeManagementTranslations) }};
    </script>
    <script src="{{ hyperAsset('assets/js/attribute.js') }}" defer></script>
@endpush
