@extends('layouts.seller.app', ['page' => $menuSeller['shipping']['active'] ?? 'shipping'])

@php
    use App\Models\StorePackage;
    $isEdit = ($action ?? null) === 'edit';
    $isDefault = (bool) ($package->is_default ?? false);
@endphp

@section('title', $isEdit ? __('labels.edit_store_package') : __('labels.add_store_package'))

@section('header_data')
    @php
        $page_title = $isEdit ? __('labels.edit_store_package') : __('labels.add_store_package');
        $page_pretitle = __('labels.shipping_packages');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.shipping_and_delivery'), 'url' => route('seller.shipping.index')],
        ['title' => __('labels.shipping_packages'), 'url' => route('seller.shipping.packages.index')],
        ['title' => $isEdit ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];

    $formAction = $isEdit
        ? route('seller.shipping.packages.update', ['packageId' => $package->id])
        : route('seller.shipping.packages.store');
    $listRoute = route('seller.shipping.packages.index');

    $types = [
        StorePackage::TYPE_BOX       => __('labels.package_type_box'),
        StorePackage::TYPE_ENVELOPE  => __('labels.package_type_envelope'),
        StorePackage::TYPE_SOFT_PACK => __('labels.package_type_soft_pack'),
    ];
@endphp

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        {{ $isEdit ? __('labels.edit_store_package') : __('labels.add_store_package') }}
                        @if ($isDefault)
                            <span class="badge bg-blue-lt border border-blue-subtle ms-2">
                                <i class="ti ti-star-filled me-1"></i>{{ __('labels.default_package_badge') }}
                            </span>
                        @endif
                    </h2>
                    <x-breadcrumb :items="$breadcrumbs"/>
                </div>
            </div>
        </div>

        <div class="page-body">
            @include('seller.shipping.partials._section_tabs', ['active' => 'packages'])

            <form
                class="form-submit" method="post"
                action="{{ $formAction }}">
                @csrf

                <div class="row row-cards">
                    <div class="col-lg-7">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.store_package_basics') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.store') }}</label>
                                    <select name="store_id"
                                            class="form-select js-tom-select-static"
                                            data-placeholder="{{ __('labels.select_a_store') }}"
                                            {{ $isEdit ? 'disabled' : '' }}
                                            required>
                                        @foreach ($stores as $s)
                                            <option value="{{ $s->id }}"
                                                @selected((int) old('store_id', $storeId) === (int) $s->id)>
                                                {{ $s->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($isEdit)
                                        <input type="hidden" name="store_id" value="{{ $storeId }}">
                                        <small
                                            class="form-hint">{{ __('labels.store_package_store_lock_hint') }}</small>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.name') }}</label>
                                    <input type="text"
                                           name="name"
                                           class="form-control"
                                           value="{{ old('name', $package->name) }}"
                                           required
                                           maxlength="120">
                                    <small class="form-hint">{{ __('labels.store_package_name_hint') }}</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.package_type') }}</label>
                                    <select name="type" class="form-select js-tom-select-static">
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}"
                                                @selected(old('type', $package->type ?? StorePackage::TYPE_BOX) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @php
                                        $packageHints = [
                                            StorePackage::TYPE_BOX => __('labels.package_type_box_hint'),
                                            StorePackage::TYPE_ENVELOPE => __('labels.package_type_envelope_hint'),
                                            StorePackage::TYPE_SOFT_PACK => __('labels.package_type_soft_pack_hint'),
                                        ];
                                    @endphp
                                    <small
                                        class="form-hint"
                                        id="packageTypeHint"
                                        data-hints='{{ json_encode($packageHints) }}'>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('labels.dimensions_and_weight') }}</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-secondary small mb-3">{{ __('labels.store_package_dims_hint') }}</p>

                                <div class="row g-2">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required">{{ __('labels.length') }}</label>
                                        <div class="input-group">
                                            <input type="number" min="1" max="1000" name="length_cm"
                                                   class="form-control" required
                                                   value="{{ old('length_cm', $package->length_cm ?? '') }}">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required">{{ __('labels.breadth') }}</label>
                                        <div class="input-group">
                                            <input type="number" min="1" max="1000" name="breadth_cm"
                                                   class="form-control" required
                                                   value="{{ old('breadth_cm', $package->breadth_cm ?? '') }}">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required">{{ __('labels.height') }}</label>
                                        <div class="input-group">
                                            <input type="number" min="1" max="1000" name="height_cm"
                                                   class="form-control" required
                                                   value="{{ old('height_cm', $package->height_cm ?? '') }}">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-secondary small mb-3" id="volumetricReadout"
                                     data-divisor="{{ StorePackage::VOLUMETRIC_DIVISOR }}">
                                    {{ __('labels.volumetric_weight') }}: <strong id="volumetricValue">0</strong> kg
                                    <div
                                        class="form-hint">{{ __('labels.volumetric_weight_hint', ['divisor' => StorePackage::VOLUMETRIC_DIVISOR]) }}</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.empty_weight') }}</label>
                                    <div class="input-group">
                                        <input type="number" step="0.001" min="0" max="9999.999" name="empty_weight_kg"
                                               class="form-control"
                                               value="{{ old('empty_weight_kg', $package->empty_weight_kg ?? 0) }}">
                                        <span class="input-group-text">kg</span>
                                    </div>
                                    <small class="form-hint">{{ __('labels.empty_weight_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ $listRoute }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        {{ $isEdit ? __('labels.submit') : __('labels.add_store_package') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/store-package-form.js') }}"></script>
@endpush
