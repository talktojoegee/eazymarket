@php
    use App\Enums\Order\FulfillmentStatusEnum;use App\Enums\Order\OrderItemStatusEnum;use App\Enums\Product\ProductAttachmentModeEnum;use App\Enums\Product\ProductImageFitEnum;use App\Enums\Product\ProductIndicatorEnum;use App\Enums\Product\ProductTypeEnum;use App\Enums\Product\ProductVideoTypeEnum;use App\Models\Currency;$isAdminMode = $isAdminMode ?? false;
    $formAction = $formAction ?? (empty($product)
        ? route('seller.products.store')
        : route('seller.products.update', ['id' => $product->id]));
    $indexUrl = $indexUrl ?? route('seller.products.index');
    $selectedSellerId = $selectedSellerId ?? ($product->seller_id ?? null);
    $selectedSellerName = $selectedSellerName ?? ($product->seller?->user?->name ?? '');
    $approvalCriticalFields = array_keys($approvalCriticalLabels ?? []);
@endphp

@isset($product)
    @php
        $visibilityEndpoint = $isAdminMode
            ? route('admin.products.visibility', $product->id)
            : route('seller.products.visibility', $product->id);
    @endphp
    @include('partials._visibility-inspector', ['endpoint' => $visibilityEndpoint])
@endisset

<form id="product-form-submit" method="POST"
      action="{{ $formAction }}"
      enctype="multipart/form-data" novalidate>
    @csrf

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <div class="text-muted small">
                <a href="{{ $indexUrl }}" class="text-muted text-decoration-none">
                    <i class="ti ti-arrow-left me-1"></i>{{ __('labels.back_to_products') }}
                </a>
            </div>
            <h2 class="page-title mb-0 mt-1">{{ $title }}</h2>
        </div>
    </div>

    @if(!$isAdminMode)
        <div class="product-approval-notice" id="productApprovalNotice"
             data-requires-approval="{{ !empty($product) && !empty($product->category?->requires_approval) ? '1' : '0' }}"
             data-lbl-required="{{ __('labels.category_requires_approval') }}"
             data-lbl-not-required="{{ __('labels.category_does_not_require_approval') }}"
             data-lbl-select-category="{{ __('labels.select_category_to_check_approval') }}">
            <div class="product-approval-notice-icon"><i class="ti ti-shield-check"></i></div>
            <div class="product-approval-notice-copy">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <strong>{{ __('labels.product_approval_notice_title') }}</strong>
                    @isset($product)
                        <span class="badge {{ $product->verification_status === 'approved' ? 'bg-green-lt' : ($product->verification_status === 'rejected' ? 'bg-red-lt' : 'bg-yellow-lt') }}">
                            {{ Str::headline($product->verification_status) }}
                        </span>
                    @endisset
                </div>
                <p class="mb-1" data-approval-category-message>{{ __('labels.select_category_to_check_approval') }}</p>
                <small>{{ __('labels.product_approval_minor_changes_hint') }}</small>
                @if(isset($product) && $product->verification_status === 'rejected' && filled($product->rejection_reason))
                    <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small" role="alert">
                        <strong>{{ __('labels.rejection_reason') }}:</strong> {{ $product->rejection_reason }}
                    </div>
                @endif
                <div class="product-approval-critical-fields mt-2 d-none" data-approval-critical-fields>
                    <span class="small">{{ __('labels.product_approval_critical_changes_hint') }}</span>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach(($approvalCriticalLabels ?? []) as $criticalLabel)
                            <span class="badge bg-orange-lt">{{ $criticalLabel }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @elseif(isset($product))
        <div class="product-approval-notice is-admin">
            <div class="product-approval-notice-icon"><i class="ti ti-shield-check"></i></div>
            <div class="product-approval-notice-copy">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <strong>{{ __('labels.product_approval_notice_title') }}</strong>
                    <span class="badge {{ $product->verification_status === 'approved' ? 'bg-green-lt' : ($product->verification_status === 'rejected' ? 'bg-red-lt' : 'bg-yellow-lt') }}">
                        {{ Str::headline($product->verification_status) }}
                    </span>
                </div>
                <small>{{ $product->category?->requires_approval ? __('labels.category_requires_approval') : __('labels.category_does_not_require_approval') }}</small>
                @if($product->verification_status === 'pending_verification' && ($updateStatusPermission ?? false))
                    <div class="d-flex flex-wrap gap-2 mt-2 product-approval-actions">
                        <button type="button" class="btn btn-success btn-sm approve-product" data-id="{{ $product->id }}">
                            <i class="ti ti-check me-1"></i>{{ __('labels.approve') }}
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm reject-product" data-id="{{ $product->id }}">
                            <i class="ti ti-x me-1"></i>{{ __('labels.reject') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($isAdminMode)
        <div class="product-form-picker-wrap">
            <div class="card" id="adminSellerPickerWrap">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-md-6">
                            <label class="form-label required mb-1">{{ __('labels.seller') }}</label>
                            <select id="adminSellerPicker" name="seller_id" class="form-select"
                                    placeholder="{{ __('labels.select_seller') }}" autocomplete="off">
                                @if($selectedSellerId)
                                    <option value="{{ $selectedSellerId }}"
                                            selected>{{ $selectedSellerName ?: '#' . $selectedSellerId }}</option>
                                @endif
                            </select>
                            <small class="text-muted d-block mt-1">{{ __('labels.seller_picker_hint') }}</small>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-warning mb-0 py-2 px-3 d-none" id="adminSellerChangeAlert"
                                 role="alert">
                                <i class="ti ti-alert-triangle me-1"></i>{{ __('labels.seller_change_clears_pricing') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(empty($selectedSellerId))
            <div class="product-form-locked-empty">
                <i class="ti ti-lock" aria-hidden="true"></i>
                <div>{{ __('labels.admin_pick_seller_to_continue') }}</div>
                <small class="text-muted d-block mt-1">{{ __('labels.admin_pick_seller_to_continue_hint') }}</small>
            </div>
        @endif
    @endif

    <div class="product-form-shell {{ ($isAdminMode && empty($selectedSellerId)) ? 'd-none' : '' }}"
         id="productFormShell">

        <aside class="product-form-rail" id="productFormRail">
            <a href="#section-general" class="nav-link is-active"
               data-target="section-general">{{ __('labels.general') }}</a>
            <a href="#section-info" class="nav-link" data-target="section-info">{{ __('labels.product_info') }}</a>
            <a href="#section-policies" class="nav-link"
               data-target="section-policies">{{ __('labels.policies_and_features') }}</a>
            <a href="#section-variations" class="nav-link"
               data-target="section-variations">{{ __('labels.variations') }}</a>
            <a href="#section-kit" class="nav-link d-none" data-target="section-kit" id="kitSectionNav">{{ __('labels.kit_contents') }}</a>
            <a href="#section-media" class="nav-link" data-target="section-media">{{ __('labels.images') }}</a>
            <a href="#section-description" class="nav-link"
               data-target="section-description">{{ __('labels.description') }}</a>
            <a href="#section-pricing" class="nav-link"
               data-target="section-pricing">{{ __('labels.variant_pricing_inventory') }}</a>
            <a href="#section-tax" class="nav-link" data-target="section-tax">{{ __('labels.tax') }}</a>
        </aside>

        <div class="product-form-main" id="productFormMain">

            <section class="card form-section" id="section-general">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.general') }}</h3>
                </div>
                <div class="card-body">
                    <div class="product-category-picker">
                        <div class="product-category-picker-head">
                            <div>
                                <label class="form-label required {{ in_array('category_id', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}" for="select-category">
                                    {{ __('labels.category') }}
                                    @if(in_array('category_id', $approvalCriticalFields, true))
                                        <span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>
                                    @endif
                                </label>
                                <small>{{ __('labels.search_or_browse_category') }}</small>
                            </div>
                            <div class="btn-list">
                                <button type="button" class="btn btn-sm btn-ghost-secondary" id="expandCategoryTree">{{ __('labels.expand_all') }}</button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary" id="collapseCategoryTree">{{ __('labels.collapse_all') }}</button>
                            </div>
                        </div>
                        <select class="form-select" id="select-category" aria-label="{{ __('labels.category') }}"></select>
                        <div class="product-category-selected" id="selectedCategorySummary">
                            <i class="ti ti-category"></i>
                            <span>{{ !empty($product) ? ($product->category?->title ?? __('labels.selected_category')) : __('labels.selected_category') }}</span>
                            <span class="badge ms-auto d-none" data-category-approval-badge></span>
                        </div>
                        <div id="categories" data-categories="{{ $categories }}"></div>
                        <input type="hidden" id="selected_category" name="category_id"
                               value="{{ $product->category_id ?? "" }}">
                        <div id="categories-tree" class="product-category-tree"></div>
                    </div>
                </div>
            </section>

            <section class="card form-section" id="section-info">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.product_info') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required {{ in_array('title', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.product_title') }} @if(in_array('title', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <input type="text" class="form-control" name="title" value="{{ $product->title ?? "" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label {{ in_array('brand_id', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.brand') }} @if(in_array('brand_id', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <input type="hidden" id="selected-brand" value="{{ $product->brand_id ?? "" }}">
                        <select class="form-select" name="brand_id" id="select-brand">
                            @if(!empty($product->brand))
                                <option value="{{ $product->brand_id }}"
                                        selected>{{ $product->brand->title }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.made_in') }}</label>
                        <input type="text" class="form-control" name="made_in"
                               value="{{ $product->made_in ?? "" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            {{ __('labels.hsn_code') }}
                            <x-form-info-tooltip :title="__('messages.product_hsn_code_info_message')"/>
                        </label>
                        <input type="text" class="form-control" name="hsn_code"
                               inputmode="numeric" pattern="\d{4}(\d{2}(\d{2})?)?" maxlength="8"
                               title="{{ __('labels.hsn_code_digits_only') ?? 'HSN must be 4, 6 or 8 digits' }}"
                               oninput="this.value = this.value.replace(/\D/g, '')"
                               value="{{ $product->hsn_code ?? "" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label {{ in_array('indicator', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">
                            {{ __('labels.indicator') }}
                            @if(in_array('indicator', $approvalCriticalFields, true))
                                <span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>
                            @endif
                            <x-form-info-tooltip :title="__('messages.product_indicator_info_message')"/>
                        </label>
                        <select class="form-select" name="indicator">
                            <option value="">{{ __('labels.select_indicator') }}</option>
                            @foreach(ProductIndicatorEnum::values() as $type)
                                <option
                                    value="{{ $type }}" {{ !empty($product->indicator) && $product->indicator == $type ? 'selected' : '' }}>{{ Str::replace("_" ," ", $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <label class="form-label mb-0">{{ __('labels.custom_fields') }}</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleCustomFieldsQuickAdd">
                                <i class="ti ti-wand me-1"></i>{{ __('labels.quick_add_fields') }}
                            </button>
                        </div>
                        <div class="custom-fields-quick-add d-none" id="customFieldsQuickAdd"
                             data-added-message="{{ __('labels.bulk_custom_fields_added') }}"
                             data-empty-message="{{ __('labels.bulk_custom_fields_empty') }}">
                            <div class="d-flex align-items-start gap-2 text-muted small mb-2">
                                <i class="ti ti-info-circle mt-1"></i>
                                <span>{{ __('labels.bulk_custom_fields_hint') }}</span>
                            </div>
                            <textarea class="form-control" id="customFieldsBulkInput" rows="4" placeholder="{{ __('labels.bulk_custom_fields_example') }}"></textarea>
                            <div class="d-flex justify-content-end mt-2">
                                <button type="button" class="btn btn-primary btn-sm" id="applyCustomFieldsBulk">{{ __('labels.add_bulk_fields') }}</button>
                            </div>
                        </div>
                        <div id="customFieldsContainer" class="vstack gap-2"
                             data-existing='@json($product->custom_fields ?? new \stdClass())'></div>
                        <button type="button" class="btn btn-outline-primary mt-2" id="addCustomFieldBtn">
                            <i class="ti ti-plus me-1"></i>{{ __('labels.add_field') }}
                        </button>
                        <small class="form-hint d-block mt-1">{{ __('labels.bulk_custom_fields_hint') }}</small>
                    </div>
                </div>
            </section>

            <section class="card form-section" id="section-policies">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.policies_and_features') }}</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label required">
                                    {{ __('labels.minimum_order_quantity') }}
                                    <x-form-info-tooltip
                                        :title="__('messages.product_minimum_order_quantity_info_message')"/>
                                </label>
                                <input type="number" class="form-control" name="minimum_order_quantity" min="1"
                                       value="{{ $product->minimum_order_quantity ?? "" }}">
                                <small class="form-hint">By Default Minimum Quantity is 1</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label required">
                                    {{ __('labels.quantity_step_size') }}
                                    <x-form-info-tooltip
                                        :title="__('messages.product_quantity_step_size_info_message')"/>
                                </label>
                                <input type="number" class="form-control" name="quantity_step_size" min="1"
                                       value="{{ $product->quantity_step_size ?? "" }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label required">
                                    {{ __('labels.total_allowed_quantity') }}
                                    <x-form-info-tooltip
                                        :title="__('messages.product_total_allowed_quantity_info_message')"/>
                                </label>
                                <input type="number" class="form-control" name="total_allowed_quantity" min="0"
                                       value="{{ $product->total_allowed_quantity ?? "" }}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.is_returnable') }}</label>
                                <select class="form-select" name="is_returnable" id="isReturnable">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option
                                        value="1" {{ (!empty($product->is_returnable) && $product->is_returnable == '1') ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option
                                        value="0" {{ empty($product->is_returnable) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.is_cancelable') }}</label>
                                <select class="form-select" name="is_cancelable" id="isCancelable">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option
                                        value="1" {{ (!empty($product->is_cancelable) && $product->is_cancelable == '1') ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option
                                        value="0" {{ empty($product->is_cancelable) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                @php $allowCod = old('allow_cod', $product->allow_cod ?? '1'); @endphp
                                <label class="form-label">{{ __('labels.allow_cod') }}</label>
                                <select class="form-select" name="allow_cod">
                                    <option value="1" {{ (string)$allowCod === '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option value="0" {{ (string)$allowCod === '0' ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                                <small class="text-muted">{{ __('labels.allow_cod_hint') }}</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label {{ !empty($product->is_cancelable) ? 'required' : '' }}" id="cancelableTillLabel">
                                    {{ __('labels.cancelable_till') }}
                                    <x-form-info-tooltip :title="__('messages.product_cancelable_till_info_message')"/>
                                </label>
                                <select class="form-select text-capitalize" name="cancelable_till">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    @foreach(FulfillmentStatusEnum::cancellableTillOptions() as $cancelTill)
                                        <option
                                            value="{{ (string) $cancelTill }}" {{ (!empty($product->cancelable_till) && (string) $product->cancelable_till === (string) $cancelTill) ? 'selected' : '' }}>
                                            {{ Str::replace("_", " ", (string) $cancelTill) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.is_attachment_required') }}
                                    <x-form-info-tooltip
                                        :title="__('messages.product_is_attachment_required_info_message')"/>
                                </label>
                                <select class="form-select" name="is_attachment_required">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option
                                        value="1" {{ !empty($product->is_attachment_required) && $product->is_attachment_required == '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option
                                        value="0" {{ empty($product->is_attachment_required) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.attachment_mode') }}
                                    <x-form-info-tooltip :title="__('messages.product_attachment_mode_info_message')"/>
                                </label>
                                <select class="form-select text-capitalize" name="attachment_mode">
                                    @foreach(ProductAttachmentModeEnum::values() as $value)
                                        <option
                                            value="{{$value}}" {{ !empty($product->attachment_mode) && $product->attachment_mode == $value ? 'selected' : '' }}>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.featured_product') }}
                                    <x-form-info-tooltip :title="__('messages.product_featured_info_message')"/>
                                </label>
                                <select class="form-select" name="featured">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option
                                        value="1" {{ (!empty($product->featured) && $product->featured == '1') ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option
                                        value="0" {{ empty($product->featured) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3 returnable-days">
                                <label class="form-label {{ !empty($product->is_returnable) ? 'required' : '' }}" id="returnableDaysLabel">{{ __('labels.returnable_days') }}</label>
                                <input type="number" class="form-control" name="returnable_days" min="0"
                                       value="{{ $product->returnable_days ?? "" }}">
                                <small class="form-hint">Required if Product is returnable</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.warranty_period') }}</label>
                                <input type="text" class="form-control" name="warranty_period"
                                       value="{{ $product->warranty_period ?? "" }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.guarantee_period') }}</label>
                                <input type="text" class="form-control" name="guarantee_period"
                                       value="{{ $product->guarantee_period ?? ""}}">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card form-section" id="section-variations">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.variations') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required {{ in_array('type', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.product_type') }} @if(in_array('type', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <select class="form-select text-capitalize" name="type"
                                id="productType" {{ !empty($product->type) ? 'readonly' : '' }}>
                            <option value="">{{ __('labels.select_type') }}</option>
                            @foreach(ProductTypeEnum::values() as $type)
                                <option
                                    value="{{ $type }}" {{ (!empty($product->type) && $product->type == $type) ? 'selected' : '' }}>{{ $type === 'kit' ? __('labels.kit') : $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="simpleProductSection" class="d-none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.barcode') }}</label>
                                    <input type="text" class="form-control" name="barcode"
                                           value="{{ $singleProductVariant->barcode ?? "" }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.weight') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="weight"
                                               value="{{ $singleProductVariant->weight ?? "" }}"><span
                                            class="input-group-text">kg</span>
                                    </div>

                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.height') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="height"
                                               value="{{ $singleProductVariant->height ?? "" }}"><span
                                            class="input-group-text">CM</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.length') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="length"
                                               value="{{ $singleProductVariant->length ?? "" }}"><span
                                            class="input-group-text">CM</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.breadth') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="breadth"
                                               value="{{ $singleProductVariant->breadth ?? "" }}"><span
                                            class="input-group-text">CM</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="variationsSection" class="d-none">
                        <div id="productVariantBuilder"
                             data-attribute-url="{{ route(($isAdminMode ? 'admin' : 'seller').'.products.lookup.attributes') }}"
                             data-value-url="{{ url(($isAdminMode ? 'admin' : 'seller').'/products/lookup/attributes') }}/__ATTRIBUTE__/values"
                             data-panel="{{ $isAdminMode ? 'admin' : 'seller' }}"
                             data-max-variants="100"
                             data-lbl-attribute-removed="{{ __('labels.attribute_removed_from_product_form') }}"
                             data-lbl-attribute-value-removed="{{ __('labels.attribute_value_removed_from_product_form') }}"
                             data-manage-url="{{ $isAdminMode ? route('admin.attributes.create', ['source' => 'product']) : route('seller.attributes.create', ['source' => 'product']) }}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h4 class="mb-1 {{ in_array('variants', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.attributes') }} <span class="text-danger">*</span> @if(in_array('variants', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</h4>
                                    <p class="text-muted small mb-0">Choose options first, then review exactly how the variant matrix will change.</p>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    @if(!$isAdminMode)
                                        <a class="btn btn-outline-primary" id="manageProductAttributes" href="{{ route('seller.attributes.create', ['source' => 'product']) }}" target="_blank" rel="noopener">
                                            <i class="ti ti-settings-plus me-1"></i>{{ __('labels.manage_create_attribute') }}
                                        </a>
                                    @else
                                        @can('create', \App\Models\GlobalProductAttribute::class)
                                            <a class="btn btn-outline-primary {{ $selectedSellerId ? '' : 'disabled' }}" id="manageProductAttributes"
                                               href="{{ $selectedSellerId ? route('admin.attributes.create', ['source' => 'product', 'seller_id' => $selectedSellerId]) : '#' }}"
                                               target="_blank" rel="noopener" @if(!$selectedSellerId) aria-disabled="true" tabindex="-1" @endif>
                                                <i class="ti ti-settings-plus me-1"></i>{{ __('labels.manage_create_attribute') }}
                                            </a>
                                        @endcan
                                    @endif
                                    <button type="button" class="btn btn-primary" id="addProductAttribute">
                                        <i class="ti ti-plus me-1"></i>Add option
                                    </button>
                                </div>
                            </div>
                            <div id="attributesContainer" class="vstack gap-2"></div>
                            <div id="variantBuilderMessage" class="alert alert-info mt-3 mb-0">Add an attribute and select at least one value.</div>
                            <div id="variantReview" class="card bg-body-tertiary mt-3 d-none">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge bg-blue-lt" data-review="total"></span>
                                        <span class="badge bg-green-lt" data-review="kept"></span>
                                        <span class="badge bg-azure-lt" data-review="added"></span>
                                        <span class="badge bg-red-lt" data-review="removed"></span>
                                        <span class="badge bg-yellow-lt" data-review="conflicts"></span>
                                        <button type="button" class="btn btn-success ms-auto" id="applyVariantMatrix">Review and Apply</button>
                                    </div>
                                    <div id="variantConflicts" class="mt-3 d-none"></div>
                                </div>
                            </div>
                            <div class="card mt-4" id="variantsContainer">
                                <div class="card-header">
                                    <h4 class="card-title">{{ __('labels.product_variants') }}</h4>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-vcenter card-table product-variant-table">
                                        <thead>
                                        <tr>
                                            <th>Options</th>
                                            <th>Title <span class="text-danger">*</span></th>
                                            <th>Barcode <span class="text-danger">*</span></th>
                                            <th>Available</th>
                                            <th>Default <span class="text-danger">*</span></th>
                                            <th class="w-1">Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody id="variantsList"></tbody>
                                    </table>
                                </div>
                                <div id="variantsEmpty" class="empty py-4">
                                    <p class="empty-title">No variants applied yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @include('seller.products._kit_components')

            <section class="card form-section" id="section-media">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.images') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required {{ in_array('media', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.main_image') }} @if(in_array('media', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <x-filepond_image name="main_image" imageUrl="{{$product->main_image ?? ''}}"/>
                    </div>
                    <div class="mb-3">
                        <label class="form-label {{ in_array('media', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.additional_images') }} @if(in_array('media', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <input type="file" name="additional_images[]" class="form-control"
                               data-images='@json($product->additional_images ?? [])' multiple>
                        <small class="form-hint">You can select multiple images at once</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">
                            {{ __('labels.image_fit') }}
                            <x-form-info-tooltip :title="__('messages.product_image_fit_info_message')"/>
                        </label>
                        <select class="form-select text-capitalize" name="image_fit" id="imageFitSelect">
                            @foreach(ProductImageFitEnum::values() as $value)
                                <option
                                    value="{{ $value }}" {{ (!empty($product->image_fit) && $product->image_fit == $value) ? 'selected' : '' }}>{{ Str::replace("_"," ",$value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.video_type') }}</label>
                        <select class="form-select text-capitalize" name="video_type" id="videoType">
                            <option value="">{{ __('labels.select_video_type') }}</option>
                            @foreach(ProductVideoTypeEnum::values() as $type)
                                <option
                                    value="{{ $type }}" {{ (!empty($product->video_type) && $product->video_type == $type) ? 'selected' : '' }}>{{ Str::replace("_"," ",$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.video_link') }}</label>
                        <input type="url" class="form-control" name="video_link"
                               value="{{ $product->video_link ?? "" }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label {{ in_array('media', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.video_upload') }} @if(in_array('media', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <input type="file" name="product_video" class="form-control"
                               data-image-url="{{$product->product_video ?? ""}}">
                    </div>
                </div>
            </section>

            <section class="card form-section" id="section-description">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.description') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required {{ in_array('short_description', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.short_description') }} @if(in_array('short_description', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <textarea class="form-control" name="short_description"
                                  rows="3">{{ $product->short_description ?? "" }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required {{ in_array('description', $approvalCriticalFields, true) ? 'approval-critical-label' : '' }}">{{ __('labels.description') }} @if(in_array('description', $approvalCriticalFields, true))<span title="{{ __('labels.approval_sensitive_hint') }}"><i class="ti ti-shield-exclamation"></i></span>@endif</label>
                        <textarea class="form-control hugerte-mytextarea" name="description" data-editor-height="560"
                                  rows="5">{{ $product->description ?? "" }}</textarea>
                    </div>
                    <!-- Custom Product Sections (User-friendly builder) -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ __('labels.custom_product_sections') }}</label>
                            <button type="button" class="btn btn-outline-primary" id="addSectionBtn">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_section') }}
                            </button>
                        </div>
                        <div id="customSectionsContainer" class="vstack gap-3">
                            @php $sectionIndex = 0; @endphp
                            @if(!empty($product) && $product->customProductSections)
                                @foreach($product->customProductSections as $sec)
                                    <div class="card border section-item" data-index="{{ $sectionIndex }}">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h4 class="mb-0">{{ __('labels.section') }} #<span
                                                        class="section-number">{{ $sectionIndex + 1 }}</span></h4>
                                                <div class="d-flex gap-2">
                                                    <button type="button"
                                                            class="btn btn-outline-danger remove-section">{{ __('labels.remove') }}</button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="custom_sections[{{ $sectionIndex }}][id]"
                                                   value="{{ $sec->id }}">
                                            <hr>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label
                                                        class="form-label required">{{ __('labels.title') }}</label>
                                                    <input type="text" class="form-control"
                                                           name="custom_sections[{{ $sectionIndex }}][title]"
                                                           value="{{ $sec->title }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('labels.description') }}</label>
                                                    <input type="text" class="form-control"
                                                           name="custom_sections[{{ $sectionIndex }}][description]"
                                                           value="{{ $sec->description }}">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('labels.sort_order') }}</label>
                                                    <input type="number" min="0" class="form-control"
                                                           name="custom_sections[{{ $sectionIndex }}][sort_order]"
                                                           value="{{ (int)($sec->sort_order ?? 0) }}">
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong class="mb-0">{{ __('labels.fields') }}</strong>
                                                    <button type="button"
                                                            class="btn add-field">{{ __('labels.add_field') }}</button>
                                                </div>
                                                <div class="vstack gap-2 fields-container">
                                                    @php $fieldIndex = 0; @endphp
                                                    @foreach(($sec->fields ?? []) as $fld)
                                                        <div class="border rounded p-2 field-item"
                                                             data-index="{{ $fieldIndex }}">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-2">
                                                                    <span>{{ __('labels.field') }} #<span
                                                                            class="field-number">{{ $fieldIndex + 1 }}</span></span>
                                                                <button type="button"
                                                                        class="btn btn-link text-danger p-0 remove-field">{{ __('labels.remove') }}</button>
                                                            </div>
                                                            <input type="hidden"
                                                                   name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][id]"
                                                                   value="{{ $fld->id }}">
                                                            <div class="row g-2 align-items-end">
                                                                <div class="col-md-3">
                                                                    <label
                                                                        class="form-label">{{ __('labels.title') }}</label>
                                                                    <input type="text" class="form-control"
                                                                           name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][title]"
                                                                           value="{{ $fld->title }}">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label
                                                                        class="form-label">{{ __('labels.description') }}</label>
                                                                    <input type="text" class="form-control"
                                                                           name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][description]"
                                                                           value="{{ $fld->description }}">
                                                                </div>
                                                                <div class="col-md-2">
                                                                    <label
                                                                        class="form-label">{{ __('labels.sort_order') }}</label>
                                                                    <input type="number" min="0"
                                                                           class="form-control"
                                                                           name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][sort_order]"
                                                                           value="{{ (int)($fld->pivot->sort_order ?? 0) }}">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label
                                                                        class="form-label">{{ __('labels.image') }}</label>
                                                                    <input type="file"
                                                                           class="form-control field-image-input"
                                                                           name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][image]"
                                                                           data-image-url="{{ $fld->image }}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @php $fieldIndex++; @endphp
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @php $sectionIndex++; @endphp
                                @endforeach
                            @endif
                        </div>
                        <small class="form-hint">{{ __('labels.custom_sections_hint') }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.tags') }}</label>
                        <select class="form-select product-tags" name="tags[]" multiple>
                            <option value="">{{ __('labels.select_tags') }}</option>
                            @php
                                $tags = [];
                                    if (!empty($product->tags)){
                                        $tags = is_string($product->tags)
                                            ? json_decode($product->tags, true)
                                            : ($product->tags ?? []);
                                    }
                            @endphp

                            @foreach($tags as $tag)
                                <option value="{{ $tag }}" selected>{{ $tag }}</option>
                            @endforeach
                        </select>
                        <small class="form-hint">{{ __('labels.select_multiple_tags') }}</small>
                    </div>
                    <hr>
                    <x-seo-fields :metadata="$product->metadata ?? null"/>
                </div>
            </section>

            <section class="form-section product-pricing-section" id="section-pricing">
                <div class="product-pricing-heading">
                    <h3>{{ __('labels.variant_pricing_inventory') }}</h3>
                    <p>{{ __('labels.pricing_inventory_description') }}</p>
                </div>
                <div>
                    <div id="variantInventorySection"
                         class="variant-inventory-section"
                         data-mode="{{ !empty($product) ? 'edit' : 'create' }}"
                         data-stores-endpoint-path="stores/with-locations"
                         data-product-id="{{ $product->id ?? '' }}"
                         data-lbl-pricing="{{ __('labels.variant_pricing_inventory') }}"
                         data-lbl-inventory="{{ __('labels.inventory_and_locations') }}"
                         data-lbl-price="{{ __('labels.price') }}"
                         data-lbl-special-price="{{ __('labels.special_price') }}"
                         data-lbl-cost="{{ __('labels.cost') }}"
                         data-lbl-sku="{{ __('labels.sku') }}"
                         data-base-currency-symbol="{{ optional(Currency::query()->where('is_base', true)->first())->symbol }}"
                         data-lbl-base-currency-hint="{{ __('labels.price_in_base_currency_hint') }}"
                         data-lbl-sell-from="{{ __('labels.sell_from_here') }}"
                         data-lbl-track="{{ __('labels.track_inventory') }}"
                         data-lbl-qty="{{ __('labels.qty') }}"
                         data-lbl-sell-all="{{ __('labels.sell_from_all') }}"
                         data-lbl-stop-all="{{ __('labels.stop_selling_all') }}"
                         data-lbl-apply-qty="{{ __('labels.apply_qty_to_all') }}"
                         data-lbl-apply="{{ __('labels.apply') }}"
                         data-lbl-loading="{{ __('labels.loading') }}"
                         data-lbl-no-locations="{{ __('labels.no_locations_yet_hint') }}"
                         data-lbl-variant="{{ __('labels.product_variant') }}"
                         data-lbl-price-missing="{{ __('labels.price_missing') }}"
                         data-lbl-sku-missing="{{ __('labels.sku_missing') }}"
                         data-lbl-pricing-title="{{ __('labels.pricing') }}"
                         data-lbl-availability-title="{{ __('labels.availability_by_location') }}"
                         data-lbl-availability-hint="{{ __('labels.availability_by_location_hint') }}"
                         data-lbl-kit-inventory-hint="{{ __('labels.kit_inventory_calculated_hint') }}"
                         data-lbl-kit-no-eligible-stores="{{ __('labels.kit_inventory_components_store_mismatch') }}"
                         data-lbl-enable-all="{{ __('labels.enable_all') }}"
                         data-lbl-disable-all="{{ __('labels.disable_all') }}"
                         data-lbl-set-quantity="{{ __('labels.set_quantity') }}"
                         data-lbl-apply-all="{{ __('labels.apply_to_all') }}"
                         data-lbl-location="{{ __('labels.location') }}"
                         data-lbl-available-sale="{{ __('labels.available_for_sale') }}"
                         data-lbl-track-stock="{{ __('labels.track_stock') }}"
                         data-lbl-unlimited="{{ __('labels.unlimited') }}"
                         data-lbl-product-variants="{{ __('labels.product_variants') }}"
                         data-lbl-inventory-location="{{ __('labels.inventory_location') }}"
                         data-lbl-product-issues-heading="{{ __('labels.product_issues_heading') }}">

                        <div class="alert alert-danger d-none" id="variantInventoryErrors" role="alert"></div>
                        <div id="variantInventoryAccordion"></div>
                        <div id="variantInventoryEmpty" class="card p-4 text-center text-muted small">
                            {{ __('labels.loading') }}…
                        </div>
                    </div>
                </div>
            </section>

            <section class="card form-section product-tax-section" id="section-tax">
                <div class="card-header product-section-heading">
                    <div class="product-section-icon"><i class="ti ti-receipt-tax"></i></div>
                    <div>
                        <h3 class="card-title mb-1">{{ __('labels.tax') }}</h3>
                        <small class="text-muted">{{ __('labels.tax_helptext') }}</small>
                    </div>
                </div>
                <div class="card-body">
                    @php $selectedTaxClassIds = collect($product?->taxClasses ?? [])->pluck('id')->map(fn($id) => (string)$id); @endphp
                    <div class="product-tax-settings">
                        <div class="product-tax-field">
                            <label class="form-label">{{ __('labels.tax_classes') }}</label>
                            <select class="form-select" name="tax_groups[]" id="select-tax-group"
                                    multiple data-placeholder="{{ __('labels.select_tax_classes') }}">
                                @foreach(($sellerMarkets ?? collect()) as $market)
                                    @php $pricePreview = $market->price_preview; @endphp
                                    @foreach($market->taxClasses as $taxClass)
                                        @php
                                            $taxOption = [
                                                'value' => (string)$taxClass->id,
                                                'text' => $taxClass->title . ' (' . $market->name . ')',
                                                'market' => $market->name,
                                                'rate' => (float)$taxClass->taxRates->sum('rate'),
                                                'currency_code' => $pricePreview['currency_code'],
                                                'currency_symbol' => $pricePreview['currency_symbol'],
                                                'fx_rate' => $pricePreview['fx_rate'],
                                                'fx_fallback' => (bool)($pricePreview['fx_fallback'] ?? false),
                                                'currency_format' => $pricePreview['format'],
                                            ];
                                        @endphp
                                        <option value="{{ $taxClass->id }}"
                                                data-market="{{ $market->name }}"
                                                data-rate="{{ $taxOption['rate'] }}"
                                                data-data='@json($taxOption)'
                                                @selected($selectedTaxClassIds->contains((string)$taxClass->id))>{{ $taxOption['text'] }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <small class="form-hint mt-2 d-block">{{ __('labels.tax_classes_hint') }}</small>
                        </div>
                        <label class="product-tax-mode">
                            <span>
                                <strong>{{ __('labels.is_inclusive_tax') }}</strong>
                                <small>{{ __('messages.product_is_inclusive_tax_info_message') }}</small>
                            </span>
                            <span class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="is_inclusive_tax" value="1"
                                       id="isInclusiveTax" @checked((string)($product->is_inclusive_tax ?? '0') === '1')>
                            </span>
                        </label>
                    </div>
                    <div class="product-tax-preview" id="productTaxPreview" aria-live="polite"
                         data-base-currency-code="{{ \App\Support\PricePresenter::baseCode() }}"
                         data-lbl-title="{{ __('labels.tax_market_price_preview') }}"
                         data-lbl-empty="{{ __('labels.tax_preview_select_hint') }}"
                         data-lbl-tax-included="{{ __('labels.tax_included') }}"
                         data-lbl-tax-added="{{ __('labels.tax_added_at_checkout') }}"
                         data-lbl-selling-price="{{ __('labels.selling_price') }}"
                         data-lbl-tax-amount="{{ __('labels.tax_amount') }}"
                         data-lbl-customer-price="{{ __('labels.customer_price') }}"
                         data-lbl-fx-rate="{{ __('labels.fx_rate') }}"
                         data-lbl-fx-fallback="{{ __('labels.fx_base_fallback') }}"
                         data-lbl-one-base-equals-market="{{ __('labels.one_base_equals_market') }}"></div>
                </div>
            </section>

        </div>
    </div>
    <div class="product-form-save-bar" id="productFormSaveBar">
        <div class="status"><span class="dot"></span>{{ __('labels.unsaved_changes') }}</div>
        <div class="d-flex gap-2">
            <a href="{{ $indexUrl }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>{{ __('labels.save_product') }}
            </button>
        </div>
    </div>
</form>
<div class="modal fade" id="addRemovedVariantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Removed Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="removedVariantsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
