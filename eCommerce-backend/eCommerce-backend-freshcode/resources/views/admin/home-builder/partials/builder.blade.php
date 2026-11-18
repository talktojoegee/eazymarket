<div class="hb-root"
     id="home-builder-{{ $platform }}"
     data-home-builder
     data-platform="{{ $platform }}"
     data-show-url="{{ route('admin.home-builder.show') }}"
     data-preview-catalog-url="{{ route('admin.home-builder.preview-catalog') }}"
     data-save-url="{{ route('admin.home-builder.save') }}"
     data-section-media-url="{{ url('admin/home-builder/sections') }}"
     data-item-image-url="{{ url('admin/home-builder/items') }}"
     data-product-search="{{ url('admin/products/search') }}"
     data-category-search="{{ url('admin/categories/search') }}"
     data-brand-search="{{ url('admin/brands/search') }}"
     data-market-search="{{ url('admin/markets/search-tom') }}"
     data-can-edit="{{ $canEdit ? '1' : '0' }}"
     data-labels='@json($labels)'
     data-options='@json($options)'
     data-templates='@json($templates ?? [])'
     data-themes='@json($themes ?? [])'
     data-section-types='@json($options['section_types'])'
     data-android-mockup="{{ asset('assets/images/android-mockup.png') }}"
     data-iphone-mockup="{{ asset('assets/images/iphone-mockup.png') }}"
     data-storefront-settings='@json($storefrontSettings ?? [])'
     data-home-general-settings='@json($homeGeneralSettings ?? [])'
     data-preview-catalog='@json($previewCatalog ?? [])'
     data-navbar-items='@json($homeNavbarItems ?? [])'>

    <div class="hb-header-mode-bar" data-role="header-mode-bar" hidden>
        <div>
            <i class="ti ti-layout-navbar"></i>
            <span><strong>{{ $platform === 'app' ? 'App' : 'Web' }} header</strong><small>Drag navbar items in the preview to reorder them.</small></span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-role="exit-header-mode">
            <i class="ti ti-arrow-left me-1"></i>Back to home page
        </button>
    </div>

    <div class="row align-items-center mb-3 hb-builder-topbar">
        <div class="col min-w-0">
            <div class="hb-scope-toolbar">
                <span class="text-muted">{{ __('labels.hb_scope') }}</span>
                <div class="hb-scope-category-slider" data-role="scope-category-slider" aria-label="Layout scope">
                    <button type="button" class="hb-scope-category-nav" data-scope-category-scroll="-1" aria-label="Previous categories" hidden>
                        <i class="ti ti-chevron-left"></i>
                    </button>
                    <div class="hb-scope-category-track" data-role="scope-category-track"></div>
                    <button type="button" class="hb-scope-category-nav" data-scope-category-scroll="1" aria-label="Next categories" hidden>
                        <i class="ti ti-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-auto hb-market-view-control">
            <div class="hb-market-picker-box">
                <span class="hb-market-picker-label"><i class="ti ti-world me-1"></i>Market View:</span>
                <div class="d-flex align-items-center gap-1">
                    <select id="hb-preview-market-{{ $platform }}" class="form-select hb-market-select" data-role="preview-market">
                        <option value="">Default (All Markets)</option>
                    </select>
                    <button type="button" class="btn btn-icon btn-outline-warning d-none hb-reset-market-btn" data-role="reset-market-order"
                            title="Reset this market to default section order" @disabled(! $canEdit)>
                        <i class="ti ti-restore"></i>
                    </button>
                </div>
            </div>
            <small class="text-muted d-block mt-1 hb-market-status-text" data-role="market-view-status">Previewing default homepage order</small>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-outline-primary" data-role="open-templates" @disabled(! $canEdit)>
                <i class="ti ti-template me-1"></i>Ready-made Templates
            </button>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-outline-primary hb-issues-toggle" data-role="issues-toggle" aria-expanded="false">
                <i class="ti ti-alert-triangle me-1"></i><span data-role="issues-label">Checking…</span>
                <span class="badge ms-1" data-role="issues-count">0</span>
            </button>
        </div>
        <div class="col-auto hb-save-control">
            <span class="hb-save-status" data-role="save-status" data-state="loading"><i></i><span>Loading…</span></span>
            <button type="button" class="btn btn-primary" data-role="save" @disabled(! $canEdit)>
                <i class="ti ti-device-floppy me-1"></i>{{ __('labels.hb_save') }}
            </button>
        </div>
    </div>

    <div class="hb-issues-panel" data-role="issues-panel" hidden></div>

    <div class="hb-builder-workspace">
        <div class="hb-builder-canvas" data-role="builder-canvas">
            <div class="card hb-preview-shell" data-role="preview-shell">
                <div class="card-body p-0" data-role="preview"></div>
            </div>

            <div class="card hb-sections-card hb-structure-panel" data-role="structure-panel">
                <div class="card-header hb-structure-header" data-role="structure-drag-handle">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <i class="ti ti-grip-horizontal hb-structure-grip"></i>
                        <i class="ti ti-stack-2"></i>
                        <h3 class="card-title text-truncate">{{ __('labels.hb_sections') }}</h3>
                        <span class="badge bg-secondary-lt hb-section-count" data-role="section-count">0</span>
                    </div>
                    <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary"
                            data-role="structure-toggle" title="Toggle sections">
                        <i class="ti ti-chevron-up"></i>
                    </button>
                </div>
                <div class="card-body hb-structure-body" data-role="structure-body">
                    <div data-role="sections" class="hb-sections"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2 hb-dashed"
                            data-role="add-section" @disabled(! $canEdit)>
                        <i class="ti ti-plus me-1"></i>{{ __('labels.hb_add_section') }}
                    </button>
                    <div data-role="type-picker" class="hb-type-picker d-none mt-2"></div>
                </div>
            </div>
        </div>

        <aside class="hb-builder-inspector">
            <div class="card hb-editor-card">
                <div class="hb-inspector-title">
                    <i class="ti ti-adjustments-horizontal"></i>
                    <span>Design</span>
                    <button type="button" class="btn btn-sm btn-outline-primary hb-inspector-navbar-add"
                            data-role="new-navbar" hidden @disabled(! $canEdit)>
                        <i class="ti ti-plus me-1"></i>New navbar
                    </button>
                </div>
                <div class="card-body p-0" data-role="editor">
                    <div class="hb-editor-empty text-muted">{{ __('labels.hb_select_section') }}</div>
                </div>
            </div>
        </aside>
    </div>
</div>
