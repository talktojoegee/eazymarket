@php
    $hcLabels = [
        'all'              => __('labels.all'),
        'global'           => __('labels.global'),
        'appearance'       => __('labels.appearance'),
        'barTitle'         => __('labels.bar_title'),
        'categoryTitle'    => __('labels.category_title'),
        'icon'             => __('labels.icon'),
        'activeIcon'       => __('labels.active_icon'),
        'backgroundType'   => __('labels.background_type'),
        'none'             => __('labels.none'),
        'color'            => __('labels.color'),
        'gradient'         => 'Gradient',
        'image'            => __('labels.image'),
        'lottie'           => 'Lottie',
        'backgroundColor'  => __('labels.background_color'),
        'backgroundImage'  => __('labels.background_image'),
        'backgroundLottie' => 'Header background animation',
        'fontColor'        => __('labels.font_color'),
        'searchLabels'     => __('labels.search_labels'),
        'save'             => __('labels.save'),
        'saveAppearance'   => __('labels.save_appearance'),
        'upload'           => __('labels.hb_upload'),
        'change'           => __('labels.change'),
        'remove'           => __('labels.remove'),
        'globalAppearance' => __('labels.global_appearance'),
        'addLabel'         => __('labels.add'),
        'itemTitle'        => 'Navbar title',
        'slug'             => 'URL key',
        'active'           => 'Active',
        'showOnApp'        => 'Show on app',
        'showOnWeb'        => 'Show on web',
    ];
@endphp

<div id="home-categories-manager"
     data-list-url="{{ route('admin.home-navbar-items.index') }}"
     data-create-url="{{ route('admin.home-navbar-items.store') }}"
     data-order-url="{{ route('admin.home-navbar-items.order') }}"
     data-item-base="{{ url('admin/home-navbar-items') }}"
     data-global-show-url="{{ route('admin.home-builder.global-appearance.show') }}"
     data-global-save-url="{{ route('admin.home-builder.global-appearance.save') }}"
     data-can-edit="{{ $canEdit ? '1' : '0' }}"
     data-empty-text="No custom navbar items yet. Add Rain, Summer, a category, or any other home view."
     data-labels='@json($hcLabels)'>

    <div class="row g-3">
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                <div>
                    <h3 class="card-title m-0">Custom navbar items</h3>
                    <div class="text-secondary small"><span id="hc-count">0</span> custom home views · drag to reorder for the selected platform</div>
                </div>
                <button type="button" class="btn btn-primary" id="hc-save" @disabled(! $canEdit)>
                    <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save_sorting') }}
                </button>
            </div>

            <div class="hc-sort-toolbar mb-3">
                <span class="hc-sort-label">Ordering</span>
                <div class="hc-sort-switcher" id="hc-sort-platform" role="group" aria-label="Sorting platform">
                    <button type="button" class="hc-sort-option active" data-sort-platform="app" aria-pressed="true">
                        <i class="ti ti-device-mobile"></i><span>App</span>
                    </button>
                    <button type="button" class="hc-sort-option" data-sort-platform="web" aria-pressed="false">
                        <i class="ti ti-device-desktop"></i><span>Web</span>
                    </button>
                </div>
            </div>

            <div id="hc-all-row" class="hc-all-row" role="button" tabindex="0">
                <i class="ti ti-world hc-all-icon" aria-hidden="true"></i>
                <div class="hc-all-text">
                    <div class="hc-all-title" id="hc-all-title">{{ __('labels.all') }}</div>
                    <div class="hc-all-sub">{{ __('labels.global_appearance') }}</div>
                </div>
                <span class="badge bg-primary-lt">{{ __('labels.global') }}</span>
            </div>

            <div class="input-group mb-3">
                <input type="text" id="hc-create-title" class="form-control" autocomplete="off"
                       placeholder="New item, e.g. Rain">
                <button type="button" class="btn btn-primary" id="hc-create" @disabled(! $canEdit)>
                    <i class="ti ti-plus me-1"></i>{{ __('labels.add') }}
                </button>
            </div>

            <div id="hc-list"></div>
        </div>

        <div class="col-12 col-lg-7 col-xl-8">
            <div class="card">
                <div class="card-body" id="hc-editor">
                    <div class="text-muted">{{ __('labels.hc_select_target') }}</div>
                </div>
            </div>
        </div>
    </div>

    <template id="hc-media-control-template">
        <x-media-control
            id="hc-media-control-template-instance"
            name="navbar_background"
            config-name="navbar_background_config"
            label="Navbar background"
            :disabled="! $canEdit"
            :show-config="false"
        />
    </template>

    <div class="modal modal-blur fade" id="hcImageModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('labels.hb_upload') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.cancel') }}"></button>
                </div>
                <div class="modal-body">
                    <input type="file" id="hcImagePond" name="hcImagePond">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="hcImageDone">{{ __('labels.confirm') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
