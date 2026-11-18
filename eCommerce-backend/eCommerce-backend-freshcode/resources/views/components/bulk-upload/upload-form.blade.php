@props([
    'moduleConfig',
    'availableFields',
    'availableMatchFields' => [],
    'activeJob' => null,
    'panelName' => 'admin',
    'sellerId' => null,
    'sellers' => collect(),
    'canExport' => false,
    'currencyCode' => 'USD',
    'currencySymbol' => '$',
])

@php
    $moduleKey = $moduleConfig->key;
    $maxFileSizeMb = (int) ceil((int) config('bulk_upload.max_file_kb', 102400) / 1024);
    $previewRouteToken = '00000000-0000-4000-8000-000000000000';
    $productPreviewIndexUrl = $moduleKey === 'products' ? route($panelName.'.products.bulk-upload.preview.index', ['token' => $previewRouteToken, 'seller_id' => $sellerId]) : null;
    $productPreviewProductsUrl = $moduleKey === 'products' ? route($panelName.'.products.bulk-upload.preview.products', ['token' => $previewRouteToken, 'seller_id' => $sellerId]) : null;
    $productPreviewAttributesUrl = $moduleKey === 'products' ? route($panelName.'.products.bulk-upload.preview.attributes', ['token' => $previewRouteToken, 'seller_id' => $sellerId]) : null;
    $productPreviewDetailUrl = $moduleKey === 'products' ? route($panelName.'.products.bulk-upload.preview.products.show', ['token' => $previewRouteToken, 'previewProduct' => 0, 'seller_id' => $sellerId]) : null;
    $productPreviewDestroyUrl = $moduleKey === 'products' ? route($panelName.'.products.bulk-upload.preview.destroy', ['token' => $previewRouteToken, 'seller_id' => $sellerId]) : null;
@endphp

@push('styles')
    <link href="{{ hyperAsset('assets/admin/css/catalog-bulk-upload.css') }}" rel="stylesheet">
    @if($moduleKey === 'products')
        <link href="{{ hyperAsset('assets/css/product-list.css') }}" rel="stylesheet">
        <link href="{{ hyperAsset('assets/css/product-detail.css') }}" rel="stylesheet">
    @endif
@endpush

@push('scripts')
    <script>
        window.CATALOG_BULK_UPLOAD_CFG = {
            module: @json($moduleKey),
            previewUrl: @json(route("{$panelName}.{$moduleKey}.bulk-upload.preview")),
            confirmUrl: @json(route("{$panelName}.{$moduleKey}.bulk-upload.confirm")),
            indexUrl: @json(route($panelName.'.'.$moduleKey.'.index')),
            sellerId: @json($sellerId),
            requiredCreateMapping: @json($moduleKey === 'products' ? 'handle' : 'title'),
            csrf: @json(csrf_token()),
            maxFileSize: @json($maxFileSizeMb.'MB'),
            productPreviewIndexUrl: @json($productPreviewIndexUrl),
            productPreviewProductsUrl: @json($productPreviewProductsUrl),
            productPreviewAttributesUrl: @json($productPreviewAttributesUrl),
            productPreviewDetailUrl: @json($productPreviewDetailUrl),
            productPreviewDestroyUrl: @json($productPreviewDestroyUrl),
            currencyCode: @json($currencyCode),
            currencySymbol: @json($currencySymbol),
            productPlaceholderUrl: @json(asset('assets/images/product-placeholder.jpg')),
            availableFields: @json(collect($availableFields)->map(fn ($field) => array_merge($field, ['label' => __($field['label'])]))->values()),
            availableMatchFields: @json(collect($availableMatchFields)->map(fn ($field) => array_merge($field, ['label' => __($field['label'])]))->values()),
            i18n: {
                queued: @json(__('labels.bulk_upload_queued')),
                map_required: @json(__('labels.bulk_upload_title_mapping_required')),
                pick_column: @json(__('labels.bulk_upload_pick_column')),
                upload_failed: @json(__('labels.upload_failed_generic')),
                unexpected_error: @json(__('labels.unexpected_error')),
                preview_total: @json(__('labels.bulk_upload_preview_total')),
                preview_showing: @json(__('labels.bulk_upload_preview_showing')),
                blank_rows_ignored: @json(__('labels.bulk_upload_blank_rows_ignored')),
                match_key_hint: @json(__('labels.bulk_upload_match_key_hint')),
                missing_media_title: @json(__('labels.bulk_upload_missing_media_title')),
                missing_media_update_title: @json(__('labels.bulk_upload_missing_media_update_title')),
                missing_media_create: @json(__('labels.bulk_upload_missing_media_create')),
                missing_media_update: @json(__('labels.bulk_upload_missing_media_update')),
                missing_media_create_guidance: @json(__('labels.bulk_upload_missing_media_create_guidance')),
                missing_media_update_guidance: @json(__('labels.bulk_upload_missing_media_update_guidance')),
                missing_media_create_mode: @json(__('labels.bulk_upload_mode_create_short')),
                missing_media_update_mode: @json(__('labels.bulk_upload_mode_update_short')),
                missing_media_showing: @json(__('labels.bulk_upload_missing_media_showing')),
                missing_media_more: @json(__('labels.bulk_upload_missing_media_more')),
                missing_media_rows_count: @json(__('labels.bulk_upload_rows_count')),
                untitled_row: @json(__('labels.bulk_upload_untitled_row')),
                image_preview: @json(__('labels.bulk_upload_image_preview')),
                open_image: @json(__('labels.bulk_upload_open_image')),
                preview_unavailable: @json(__('labels.bulk_upload_image_preview_unavailable')),
                product_summary: @json(__('labels.bulk_upload_product_preview_summary')),
                non_contiguous: @json(__('labels.bulk_upload_product_non_contiguous_preview')),
                duplicate_identifiers: @json(__('labels.bulk_upload_product_duplicate_identifiers_preview')),
                product_preview_title: @json(__('labels.bulk_upload_product_review_title')),
                product_preview_building: @json(__('labels.bulk_upload_product_preview_building')),
                product_preview_mapping_ready: @json(__('labels.bulk_upload_product_preview_mapping_ready')),
                product_preview_stale: @json(__('labels.bulk_upload_product_preview_stale')),
                product_preview_failed: @json(__('labels.bulk_upload_product_preview_failed')),
                product_preview_empty: @json(__('labels.bulk_upload_product_preview_empty')),
                product_preview_has_critical: @json(__('labels.bulk_upload_product_preview_has_critical')),
                product_preview_ready: @json(__('labels.bulk_upload_product_preview_ready')),
                product_preview_critical: @json(__('labels.bulk_upload_product_preview_critical')),
                product_preview_warnings: @json(__('labels.bulk_upload_product_preview_warnings')),
                product_preview_critical_label: @json(__('labels.bulk_upload_product_preview_critical_label')),
                product_preview_warning_label: @json(__('labels.bulk_upload_product_preview_warning_label')),
                product_preview_new_attributes: @json(__('labels.bulk_upload_product_preview_new_attributes')),
                product_preview_existing_attributes: @json(__('labels.bulk_upload_product_preview_existing_attributes')),
                product_preview_attribute_list_more: @json(__('labels.bulk_upload_product_preview_attribute_list_more')),
                product_preview_attribute_values: @json(__('labels.bulk_upload_product_preview_attribute_values')),
                product_preview_new: @json(__('labels.bulk_upload_product_preview_new')),
                product_preview_existing: @json(__('labels.bulk_upload_product_preview_existing')),
                product_preview_no_attributes: @json(__('labels.bulk_upload_product_preview_no_attributes')),
                product_preview_view_all_attributes: @json(__('labels.bulk_upload_product_preview_view_all_attributes')),
                product_preview_attribute_search: @json(__('labels.bulk_upload_product_preview_attribute_search')),
                product_preview_attribute_page: @json(__('labels.bulk_upload_product_preview_attribute_page')),
                product_preview_all_attributes: @json(__('labels.bulk_upload_product_preview_all_attributes')),
                product_preview_rows: @json(__('labels.bulk_upload_product_preview_source_rows')),
                product_preview_details: @json(__('labels.details')),
                product_preview_no_image: @json(__('labels.no_image')),
                products: @json(__('labels.products')),
                variants: @json(__('labels.variants')),
                locations: @json(__('labels.locations')),
                category: @json(__('labels.category')),
                brand: @json(__('labels.brand')),
                barcode: @json(__('labels.barcode')),
                price: @json(__('labels.price')),
                special_price: @json(__('labels.special_price')),
                cost: @json(__('labels.cost')),
                quantity: @json(__('labels.quantity')),
                track_inventory: @json(__('labels.track_inventory')),
                shipping: @json(__('labels.shipping')),
                tax_classes: @json(__('labels.tax_classes')),
                product_preview_type_status: @json(__('labels.bulk_upload_product_preview_type_status')),
                product_preview_variants_inventory: @json(__('labels.bulk_upload_product_preview_variants_inventory')),
                product_preview_no_variants: @json(__('labels.bulk_upload_product_preview_no_variants')),
                product_preview_untitled: @json(__('labels.bulk_upload_product_preview_untitled')),
                product_preview_page: @json(__('labels.bulk_upload_product_preview_page')),
                product_preview_pagination: @json(__('labels.bulk_upload_product_preview_pagination')),
                previous: @json(__('labels.previous')),
                next: @json(__('labels.next')),
                product_unique_locations: @json(__('labels.bulk_upload_product_unique_locations')),
                source_rows: @json(__('labels.bulk_upload_product_preview_source_rows')),
                overview: @json(__('labels.overview')),
                media: @json(__('labels.product_media')),
            },
        };
    </script>
    <script src="{{ hyperAsset('assets/admin/js/catalog-bulk-upload.js') }}" defer></script>
    @if($moduleKey === 'products')
        <script src="{{ hyperAsset('assets/admin/js/product-bulk-preview.js') }}" defer></script>
    @endif
@endpush

<div class="row g-4">
    @if($activeJob)
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <div class="alert-title">{{ __('labels.bulk_upload_already_in_progress') }}</div>
                <p class="mb-2">
                    {{ __('labels.bulk_upload_active_job_notice', [
                        'file' => $activeJob->original_filename,
                        'processed' => $activeJob->processed_rows,
                        'total' => $activeJob->total_rows,
                    ]) }}
                </p>
                <a href="{{ route($panelName.'.'.$moduleKey.'.bulk-upload.jobs.show', ['id' => $activeJob->id]) }}"
                   class="btn btn-outline-primary">
                    {{ __('labels.bulk_upload_view_active_job') }}
                </a>
            </div>
        </div>
    @else
        <div class="col-12">
            <section class="card bulk-upload-instructions overflow-hidden"
                     aria-labelledby="bulk-upload-instructions-title">
                <div class="card-body">
                    <details class="bulk-upload-help">
                        <summary id="bulk-upload-instructions-title">
                            <span><i class="ti ti-file-spreadsheet me-2" aria-hidden="true"></i>{{ __('labels.bulk_upload_instructions_title') }}</span>
                            <span class="small text-muted">{{ __('labels.bulk_upload_instructions_subtitle') }} <i class="ti ti-chevron-down ms-2" aria-hidden="true"></i></span>
                        </summary>
                        <div class="bulk-upload-help-content">
                            <ol class="bulk-upload-steps mb-0">
                        @foreach([
                            ['title' => __('labels.bulk_upload_step1_title'), 'description' => __('labels.bulk_upload_step1_hint', ['size' => $maxFileSizeMb])],
                            ['title' => __('labels.bulk_upload_step2_title'), 'description' => __('labels.bulk_upload_step2_hint')],
                            ['title' => __('labels.bulk_upload_step3_title'), 'description' => __('labels.bulk_upload_step3_hint')],
                        ] as $instruction)
                            <li><strong>{{ $instruction['title'] }}</strong><span class="text-muted">{{ $instruction['description'] }}</span></li>
                        @endforeach
                            </ol>
                            <p class="small text-muted mb-0 mt-3">{{ __('labels.bulk_upload_media_hosting_hint') }}</p>
                        </div>
                    </details>

                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 pt-3 border-top">
                        <div>
                            <div class="fw-semibold">{{ __('labels.bulk_upload_template_title') }}</div>
                            <div class="small text-muted">{{ __('labels.bulk_upload_template_help') }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($moduleConfig->templates as $templateKey => $templateLabel)
                                <a href="{{ route($panelName.'.'.$moduleKey.'.bulk-upload.template', ['template' => $templateKey]) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-download me-1" aria-hidden="true"></i>
                                    {{ __($templateLabel) }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                </div>
            </section>
        </div>

        @if($moduleKey === 'products')
            <div class="col-12">
                <div class="card bulk-upload-product-guide overflow-hidden">
                    <div class="card-body">
                        <details class="bulk-upload-help">
                            <summary><span><i class="ti ti-book-2 me-2" aria-hidden="true"></i>{{ __('labels.bulk_upload_product_guide_title') }}</span><i class="ti ti-chevron-down" aria-hidden="true"></i></summary>
                            <div class="bulk-upload-help-content">
                                <div class="bulk-upload-guide-types">
                                    <p><strong>{{ __('labels.bulk_upload_product_simple_title') }}:</strong> {{ __('labels.bulk_upload_product_simple_help') }}</p>
                                    <p><strong>{{ __('labels.bulk_upload_product_variant_title') }}:</strong> {{ __('labels.bulk_upload_product_variant_help') }}</p>
                                </div>
                                <ul class="bulk-upload-guide-list mb-0">
                                    @foreach([
                                        __('labels.bulk_upload_product_grouping_help'),
                                        __('labels.bulk_upload_product_reference_help'),
                                        __('labels.bulk_upload_product_lists_help'),
                                        __('labels.bulk_upload_product_update_help'),
                                        __('labels.bulk_upload_product_media_help'),
                                        __('labels.bulk_upload_product_default_location_help'),
                                        __('labels.bulk_upload_product_attributes_help'),
                                        __('labels.bulk_upload_product_shipping_profile_help'),
                                        __('labels.bulk_upload_product_moderation_help'),
                                    ] as $guideItem)
                                        <li>{{ $guideItem }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-2 mb-4 bulk-upload-reference-data">
                <div class="card border overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h4 class="card-title d-flex align-items-center gap-2 mb-1">
                                <i class="ti ti-id-badge-2 text-body" aria-hidden="true"></i>
                                {{ __('labels.bulk_upload_product_reference_data') }}
                            </h4>
                            <div class="small text-muted">{{ __('labels.bulk_upload_product_reference_data_help') }}</div>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        @foreach([
                            'categories-brands' => __('labels.bulk_upload_product_reference_categories_brands'),
                            'attributes' => __('labels.bulk_upload_product_reference_attributes'),
                            'locations' => __('labels.bulk_upload_product_reference_locations'),
                            'tax-classes' => __('labels.bulk_upload_product_reference_tax_classes'),
                            'shipping-profiles' => __('labels.bulk_upload_product_reference_shipping_profiles'),
                        ] as $referenceType => $referenceLabel)
                            <a class="btn btn-outline-primary btn-sm product-reference-link @if($panelName === 'admin' && !$sellerId) disabled @endif"
                               data-base-url="{{ route($panelName.'.products.bulk-upload.references', ['type' => $referenceType]) }}"
                               href="{{ route($panelName.'.products.bulk-upload.references', ['type' => $referenceType, 'seller_id' => $sellerId]) }}">
                                {{ $referenceLabel }}
                            </a>
                        @endforeach
                        @if($canExport)
                            <a class="btn btn-outline-primary btn-sm product-reference-link @if($panelName === 'admin' && !$sellerId) disabled @endif"
                               data-base-url="{{ route($panelName.'.products.bulk-upload.export') }}"
                               href="{{ route($panelName.'.products.bulk-upload.export', ['seller_id' => $sellerId]) }}">
                                {{ __('labels.bulk_upload_export_for_edit') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12" id="bulk-upload-step1">
            <form id="bulk-upload-preview-form" enctype="multipart/form-data">
                @csrf
                @if($panelName === 'admin' && $moduleConfig->sellerScoped)
                    <div class="mb-3">
                        <label class="form-label required" for="bulk-upload-seller">{{ __('labels.seller') }}</label>
                        <select class="form-select js-tom-select-remote"
                                id="bulk-upload-seller"
                                name="seller_id"
                                data-endpoint="{{ url('admin/sellers/search') }}"
                                data-placeholder="{{ __('labels.select_seller') }}"
                                data-preload="focus"
                                data-preload-limit="20"
                                data-selected='@json(array_filter([(int) $sellerId]))'
                                required>
                            <option value="">{{ __('labels.select_seller') }}</option>
                            @foreach($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected((int) $sellerId === (int) $seller->id)>
                                    #{{ $seller->id }} — {{ $seller->user?->name ?: $seller->user?->email }}
                                </option>
                            @endforeach
                        </select>
                        <div class="small text-muted mt-1">{{ __('labels.bulk_upload_product_seller_help') }}</div>
                    </div>
                @elseif($moduleConfig->sellerScoped)
                    <input type="hidden" name="seller_id" value="{{ $sellerId }}">
                @endif
                <label class="form-label required" for="catalog-bulk-csv-file">{{ __('labels.bulk_upload_choose_csv') }}</label>
                <input id="catalog-bulk-csv-file"
                       type="file"
                       name="csv_file"
                       accept=".csv,text/csv,application/vnd.ms-excel"
                       class="form-control"
                       data-label-idle="{{ __('labels.drag_drop_csv') }}"
                       required>
                <div class="small text-muted mt-1">{{ __('labels.bulk_upload_file_limit', ['size' => $maxFileSizeMb]) }}</div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" id="bulk-upload-preview-submit">
                        <i class="ti ti-eye me-1" aria-hidden="true"></i>
                        <span class="preview-text">{{ __('labels.bulk_upload_load_preview') }}</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        <div class="col-12 d-none" id="bulk-upload-step2">
            <hr>
            <h4 class="mb-3">{{ __('labels.bulk_upload_mapping_title') }}</h4>
            <div class="small text-muted mb-3" id="bulk-upload-preview-summary"></div>
            <section class="card border-warning border-2 d-none mb-4" id="bulk-upload-missing-media" aria-live="polite">
                <div class="card-header bg-warning-lt text-dark" id="bulk-upload-missing-media-header">
                    <div class="d-flex flex-wrap align-items-center gap-3 w-100">
                        <span class="avatar avatar-sm bg-warning text-dark rounded-circle"
                              id="bulk-upload-missing-media-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M15 8h.01"/>
                                <path d="M12.5 21h-5.5a4 4 0 0 1 -4 -4v-10a4 4 0 0 1 4 -4h10a4 4 0 0 1 4 4v5.5"/>
                                <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l4 4"/>
                                <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0"/>
                                <path d="M19 16v3"/>
                                <path d="M19 22v.01"/>
                            </svg>
                        </span>
                        <div class="flex-fill">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h4 class="card-title mb-0" id="bulk-upload-missing-media-title"></h4>
                                <span class="badge bg-warning text-dark" id="bulk-upload-missing-media-count"></span>
                            </div>
                            <div class="text-muted small">{{ __('labels.bulk_upload_media_check_subtitle') }}</div>
                        </div>
                        <span class="badge bg-body text-dark border border-warning ms-auto" id="bulk-upload-missing-media-mode"></span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-3" id="bulk-upload-missing-media-copy"></p>
                    <div class="alert alert-warning text-dark py-2 mb-3" id="bulk-upload-missing-media-guidance" role="note"></div>
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h5 class="mb-0">{{ __('labels.bulk_upload_affected_rows') }}</h5>
                        <span class="text-muted small" id="bulk-upload-missing-media-showing"></span>
                    </div>
                    <div class="table-responsive border rounded" style="max-height: 320px;">
                        <table class="table table-sm table-vcenter table-striped mb-0">
                            <thead class="sticky-top bg-body">
                                <tr>
                                    <th class="w-1">{{ __('labels.csv_row') }}</th>
                                    <th>{{ __('labels.record_title') }}</th>
                                    <th>{{ __('labels.missing_field') }}</th>
                                </tr>
                            </thead>
                            <tbody id="bulk-upload-missing-media-list"></tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0 mt-2 d-none" id="bulk-upload-missing-media-more"></p>
                </div>
            </section>
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('labels.bulk_upload_csv_preview') }}</h4>
                        </div>
                        <div class="table-responsive" style="max-height: 480px;">
                            <table class="table table-sm mb-0" id="bulk-upload-preview-table">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('labels.bulk_upload_field_mapping') }}</h4>
                        </div>
                        <div class="card-body">
                            <form id="bulk-upload-confirm-form">
                                @csrf
                                <input type="hidden" name="preview_token" id="preview-token">
                                <input type="hidden" name="original_filename" id="original-filename">
                                @if($moduleConfig->sellerScoped)
                                    <input type="hidden" name="seller_id" id="bulk-confirm-seller-id" value="{{ $sellerId }}">
                                @endif

                                <div class="mb-3 row align-items-center">
                                    <label class="col-sm-5 col-form-label" for="bulk-operation-select">{{ __('labels.bulk_upload_mode') }}</label>
                                    <div class="col-sm-7">
                                        <select class="form-select form-select-sm" name="operation" id="bulk-operation-select">
                                            <option value="create" selected>{{ __('labels.bulk_upload_mode_create') }}</option>
                                            @if(!empty($availableMatchFields))
                                                <option value="update">{{ __('labels.bulk_upload_mode_update') }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                @if(!empty($availableMatchFields))
                                    <div class="mb-3 row align-items-center d-none" id="bulk-match-by-row">
                                        <label class="col-sm-5 col-form-label" for="bulk-match-by-select">{{ __('labels.bulk_upload_match_by') }}</label>
                                        <div class="col-sm-7">
                                            <select class="form-select form-select-sm" name="match_field" id="bulk-match-by-select">
                                                @foreach($availableMatchFields as $matchField)
                                                    <option value="{{ $matchField['key'] }}">{{ __($matchField['label']) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif

                                <div class="mb-3 row align-items-center">
                                    <div class="col-sm-5 col-form-label">
                                        {{ __('labels.notify_me_when_finished') }}
                                        <x-form-info-tooltip :title="__('labels.notify_me_when_finished_hint')"/>
                                    </div>
                                    <div class="col-sm-7">
                                        <label class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" name="notify_on_finish" value="1">
                                            <span class="form-check-label small text-muted">{{ __('labels.notify_me_when_finished_label') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <div id="mapping-rows"></div>
                                @if($moduleKey === 'products')
                                    <section class="card bulk-upload-product-review overflow-hidden mt-4 d-none" id="bulk-product-review" aria-live="polite">
                                        <div class="card-header bulk-upload-panel-header">
                                            <div>
                                                <h4 class="card-title mb-1">{{ __('labels.bulk_upload_product_review_title') }}</h4>
                                                <div class="small text-muted" id="bulk-product-review-status">{{ __('labels.bulk_upload_product_preview_mapping_ready') }}</div>
                                            </div>
                                            <div class="card-actions d-flex flex-wrap gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="bulk-product-preview-refresh">
                                                    <i class="ti ti-refresh me-1" aria-hidden="true"></i>{{ __('labels.bulk_upload_product_preview_build') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary" id="bulk-product-preview-open" disabled>
                                                    <i class="ti ti-list-details me-1" aria-hidden="true"></i>{{ __('labels.bulk_upload_product_preview_open') }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="progress mb-3" style="height: 6px;">
                                                <div class="progress-bar bg-secondary" id="bulk-product-review-progress" style="width: 0%"></div>
                                            </div>
                                            <div class="row g-2 mb-3" id="bulk-product-review-counts"></div>
                                            <div class="row g-2 mb-3" id="bulk-product-review-attributes"></div>
                                            <div class="row g-3" id="bulk-product-review-sample"></div>
                                        </div>
                                    </section>
                                @endif
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary" id="bulk-upload-confirm-submit">
                                        <i class="ti ti-player-play me-1" aria-hidden="true"></i>
                                        <span class="confirm-text">{{ __('labels.bulk_upload_start_processing') }}</span>
                                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="bulk-upload-reset">
                                        <i class="ti ti-x me-1" aria-hidden="true"></i>
                                        {{ __('labels.cancel') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@if($moduleKey === 'products')
    <div class="modal modal-blur fade" id="bulk-product-preview-modal" tabindex="-1"
         aria-labelledby="bulk-product-preview-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title" id="bulk-product-preview-modal-title">{{ __('labels.bulk_upload_product_preview_listing') }}</h2>
                        <div class="small text-muted" id="bulk-product-preview-modal-summary"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="bulk-preview-tabs mb-3" role="tablist" aria-label="{{ __('labels.bulk_upload_product_preview_listing') }}">
                        <button type="button" class="btn btn-primary btn-sm" id="bulk-preview-products-tab" aria-selected="true">{{ __('labels.products') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="bulk-preview-attributes-tab" aria-selected="false">{{ __('labels.bulk_upload_product_preview_all_attributes') }}</button>
                    </div>
                    <div id="bulk-product-preview-list-panel">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <input type="search" class="form-control" id="bulk-product-preview-search"
                                       placeholder="{{ __('labels.bulk_upload_product_preview_search') }}">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="bulk-product-preview-issue-filter">
                                    <option value="all">{{ __('labels.all') }}</option>
                                    <option value="ready">{{ __('labels.bulk_upload_product_preview_ready') }}</option>
                                    <option value="critical">{{ __('labels.bulk_upload_product_preview_critical') }}</option>
                                    <option value="warning">{{ __('labels.bulk_upload_product_preview_warnings') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="bulk-product-preview-page-size" aria-label="{{ __('labels.per_page') }}">
                                    <option value="25">25</option><option value="50">50</option><option value="100">100</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive border rounded bulk-preview-list-table">
                            <table class="table table-vcenter table-hover mb-0">
                                <thead><tr>
                                    <th>{{ __('labels.product') }}</th><th>{{ __('labels.category') }} / {{ __('labels.brand') }}</th>
                                    <th>{{ __('labels.variants') }}</th><th>{{ __('labels.stock') }}</th><th>{{ __('labels.price') }} ({{ $currencyCode }})</th>
                                    <th>{{ __('labels.issues') }}</th><th class="w-1"></th>
                                </tr></thead>
                                <tbody id="bulk-product-preview-list"></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
                            <div class="small text-muted" id="bulk-product-preview-page-summary"></div>
                            <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                                <nav aria-label="{{ __('labels.bulk_upload_product_preview_pagination') }}">
                                    <ul class="pagination pagination-sm mb-0" id="bulk-product-preview-pagination"></ul>
                                </nav>
                                <div class="input-group input-group-sm" style="width: 155px;">
                                    <input type="number" class="form-control" id="bulk-product-preview-go-page"
                                           min="1" inputmode="numeric"
                                           aria-label="{{ __('labels.bulk_upload_product_preview_go_to_page') }}"
                                           placeholder="{{ __('labels.bulk_upload_product_preview_go_to_page') }}">
                                    <button type="button" class="btn btn-outline-primary" id="bulk-product-preview-go">
                                        {{ __('labels.bulk_upload_product_preview_go') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-none" id="bulk-product-preview-attributes-panel">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <input type="search" class="form-control flex-grow-1" style="max-width: 28rem" id="bulk-product-preview-attribute-search" placeholder="{{ __('labels.bulk_upload_product_preview_attribute_search') }}">
                            <select class="form-select w-auto" id="bulk-product-preview-attribute-status" aria-label="{{ __('labels.status') }}">
                                <option value="all">{{ __('labels.all') }}</option>
                                <option value="new">{{ __('labels.bulk_upload_product_preview_new') }}</option>
                                <option value="existing">{{ __('labels.bulk_upload_product_preview_existing') }}</option>
                            </select>
                            <select class="form-select w-auto" id="bulk-product-preview-attribute-page-size" aria-label="{{ __('labels.per_page') }}">
                                <option value="25">25</option><option value="50">50</option><option value="100">100</option>
                            </select>
                        </div>
                        <div class="table-responsive border rounded"><table class="table table-vcenter table-hover mb-0"><thead><tr><th>{{ __('labels.attribute') }}</th><th>{{ __('labels.status') }}</th></tr></thead><tbody id="bulk-product-preview-attribute-list"></tbody></table></div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"><span class="small text-muted" id="bulk-product-preview-attribute-summary"></span><nav aria-label="{{ __('labels.bulk_upload_product_preview_pagination') }}"><ul class="pagination pagination-sm mb-0" id="bulk-product-preview-attribute-pagination"></ul></nav></div>
                    </div>
                    <div class="d-none" id="bulk-product-preview-detail-panel">
                        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="bulk-product-preview-back">
                            <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>{{ __('labels.back') }}
                        </button>
                        <div id="bulk-product-preview-detail"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
