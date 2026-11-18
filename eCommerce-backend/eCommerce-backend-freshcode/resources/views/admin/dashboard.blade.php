@extends('layouts.admin.app', ['page' => $menuAdmin['dashboard']['active'] ?? ""])

@section('title', __('labels.dashboard'))

@section('header_data')
    @php
        $page_title = __('labels.dashboard');
        $page_pretitle = __('labels.admin') . " " . __('labels.dashboard');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => '']
    ];
@endphp

@section('admin-content')
    @if($viewPermission ?? false)
        @if($homeBuilderPermission ?? false)
            <section class="dashboard-home-builder mb-4" data-home-builder-showcase hidden
                     aria-labelledby="dashboard-home-builder-title">
                <button type="button" class="dashboard-home-builder__dismiss" data-home-builder-showcase-dismiss
                        aria-label="{{ __('labels.close') }}" title="{{ __('labels.close') }}">
                    <i class="ti ti-x" aria-hidden="true"></i>
                </button>
                <div class="dashboard-home-builder__content">
                    <div class="dashboard-home-builder__icon" aria-hidden="true">
                        <i class="ti ti-layout-dashboard"></i>
                    </div>
                    <div>
                        <div class="dashboard-home-builder__eyebrow">{{ __('labels.home_builder') }}</div>
                        <h2 id="dashboard-home-builder-title" class="dashboard-home-builder__title">
                            {{ __('labels.home_builder_showcase_title') }}
                        </h2>
                        <p class="dashboard-home-builder__description">
                            {{ __('labels.home_builder_showcase_description') }}
                        </p>
                        <div class="dashboard-home-builder__features" aria-label="{{ __('labels.home_builder_features') }}">
                            <span class="is-active" data-home-builder-feature><i class="ti ti-devices"></i>{{ __('labels.home_builder_app_web') }}</span>
                            <span data-home-builder-feature><i class="ti ti-shopping-bag"></i>{{ __('labels.home_builder_product_sections') }}</span>
                            <span data-home-builder-feature><i class="ti ti-layout-grid"></i>{{ __('labels.home_builder_play_image') }}</span>
                            <span data-home-builder-feature><i class="ti ti-adjustments"></i>{{ __('labels.home_builder_styles_config') }}</span>
                            <span data-home-builder-feature><i class="ti ti-navigation-plus"></i>{{ __('labels.home_builder_create_navbar') }}</span>
                            <span data-home-builder-feature><i class="ti ti-arrows-sort"></i>{{ __('labels.home_builder_sort_navbar') }}</span>
                            <span data-home-builder-feature><i class="ti ti-world-pin"></i>{{ __('labels.home_builder_scope_targeting') }}</span>
                            <span data-home-builder-feature><i class="ti ti-photo-spark"></i>{{ __('labels.home_builder_media_animation') }}</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-home-builder__actions">
                    <a href="{{ route('admin.home-builder.index') }}" class="dashboard-home-builder__demo"
                       data-home-builder-demo data-step="0" aria-label="{{ __('labels.open_home_builder') }}">
                        <span class="dashboard-home-builder__demo-window" aria-hidden="true">
                            <span class="dashboard-home-builder__demo-topbar">
                                <span class="dashboard-home-builder__demo-dots"><i></i><i></i><i></i></span>
                                <strong>Home Builder</strong>
                                <em><i></i>{{ __('labels.home_builder_live') }}</em>
                            </span>
                            <span class="dashboard-home-builder__demo-workspace">
                                <span class="dashboard-home-builder__demo-scene demo-scene-app-web" data-demo-scene="0">
                                    <span class="demo-scene-header"><b>App &amp; Web layouts</b><small>Independent storefronts</small></span>
                                    <span class="demo-platform-shell">
                                        <span class="demo-platform-list"><b>Layout</b><i class="active" data-demo-cursor-target><span class="ti ti-device-mobile"></span> App layout</i><i data-demo-cursor-target><span class="ti ti-device-desktop"></span> Web layout</i><small>Each layout keeps its own sections and order.</small></span>
                                        <span class="demo-live-preview demo-platform-preview">
                                            <b><i class="ti ti-eye"></i> Live preview <em>APP</em></b>
                                            <span class="demo-storefront"><i class="hero"></i><i class="categories"></i><i class="products"></i></span>
                                        </span>
                                        <span class="demo-platform-config"><b>Canvas</b><i class="active">App</i><i>Web</i><u><span class="ti ti-device-floppy"></span> Save layout</u></span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-product" data-demo-scene="1">
                                    <span class="demo-scene-header"><b>Product sections</b><small>Content &amp; card layout</small></span>
                                    <span class="demo-product-shell">
                                        <span class="demo-section-list"><b>Sections</b><i>⋮⋮ Hero</i><i>⋮⋮ Categories</i><i class="active" data-demo-cursor-target>⋮⋮ Products</i><i>⋮⋮ Brands</i></span>
                                        <span class="demo-live-preview">
                                            <b><i class="ti ti-eye"></i> Live preview</b>
                                            <span class="demo-product-preview"><i><u></u><small>Summer shirt</small></i><i><u></u><small>Classic bag</small></i><i><u></u><small>New sneaker</small></i></span>
                                        </span>
                                        <span class="demo-product-config"><b>Product source</b><i class="active" data-demo-cursor-target>Featured</i><i>Newest</i><b>Card style</b><i class="demo-product-grid" data-demo-cursor-target>Grid · 3</i></span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-section-create" data-demo-scene="2">
                                    <span class="demo-scene-header"><b>Play / Image layouts</b><small>Create &amp; reorder</small></span>
                                    <span class="demo-play-shell">
                                        <span class="demo-section-list"><i class="sort-hero">⋮⋮ Hero</i><i class="sort-categories">⋮⋮ Categories</i><i class="demo-new-section sort-play" data-demo-cursor-target data-demo-cursor-order="3" data-demo-cursor-drag-y="-23">⋮⋮ Play / Image</i><i class="sort-products">⋮⋮ Products</i><u data-demo-cursor-target data-demo-cursor-order="1">＋ Add section</u></span>
                                        <span class="demo-live-preview">
                                            <b><i class="ti ti-eye"></i> Live preview</b>
                                            <span class="demo-storefront"><i class="hero"></i><i class="categories"></i><i class="play-created"></i><i class="products"></i></span>
                                        </span>
                                        <span class="demo-section-picker"><b>Choose section</b><i>Products</i><i>Categories</i><i class="selected" data-demo-cursor-target data-demo-cursor-order="2">Play / Image</i><i>Brands</i></span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-section-edit" data-demo-scene="3">
                                    <span class="demo-scene-header"><b>Styles &amp; configuration</b><small>Edit Play / Image</small></span>
                                    <span class="demo-edit-shell">
                                        <span class="demo-live-preview">
                                            <b><i class="ti ti-eye"></i> Live preview</b>
                                            <span class="demo-image-preview"><i></i><i></i><i></i><i></i></span>
                                        </span>
                                        <span class="demo-edit-controls">
                                            <b class="demo-inspector-title"><i class="ti ti-adjustments-horizontal"></i> Design</b>
                                            <b>Layout style</b>
                                            <span><i class="active">Grid</i><i class="demo-scroll-option" data-demo-cursor-target>Scroll</i></span>
                                            <b>Hover effect</b>
                                            <span><i>Zoom</i><i class="demo-lift-option" data-demo-cursor-target>Lift</i></span>
                                            <u data-demo-cursor-target><i class="ti ti-photo-plus"></i> Add image</u>
                                        </span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-navbar-create" data-demo-scene="4">
                                    <span class="demo-scene-header"><b>Create &amp; configure navbar</b><small>Custom home views</small></span>
                                    <span class="demo-navbar-shell">
                                        <span class="demo-navbar-list"><i class="active">All</i><i>Fashion</i><i>Grocery</i><i class="demo-navbar-created">Summer Deals</i></span>
                                        <span class="demo-navbar-editor">
                                            <b>Create navbar view</b>
                                            <span class="demo-input" data-demo-cursor-target><i>Summer Deals</i></span>
                                            <span class="demo-create-button" data-demo-cursor-target>＋ Create</span>
                                            <span class="demo-editor-options"><i>Title</i><i data-demo-cursor-target>Image</i><i>Lottie</i></span>
                                            <span class="demo-navbar-preview"><small>Live preview</small><i>All</i><i>Fashion</i><i class="preview-summer">Summer Deals</i></span>
                                        </span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-navbar-sort" data-demo-scene="5">
                                    <span class="demo-scene-header"><b>App / Web navbar sorting</b><span><i class="active" data-demo-cursor-target>App</i><i>Web</i></span></span>
                                    <span class="demo-sort-board">
                                        <i class="demo-sort-row row-home"><small>⋮⋮</small><strong>Home</strong><u>01</u></i>
                                        <i class="demo-sort-row row-summer" data-demo-cursor-target data-demo-cursor-drag-y="-28"><small>⋮⋮</small><strong>Summer Deals</strong><u>02</u></i>
                                        <i class="demo-sort-row row-fashion"><small>⋮⋮</small><strong>Fashion</strong><u>03</u></i>
                                        <i class="demo-sort-row row-grocery"><small>⋮⋮</small><strong>Grocery</strong><u>04</u></i>
                                    </span>
                                    <span class="demo-save-order" data-demo-cursor-target><i class="ti ti-device-floppy"></i> Save sorting</span>
                                    <span class="demo-navbar-preview demo-navbar-sort-preview"><small>Live preview</small><i class="preview-home">Home</i><i class="preview-summer">Summer</i><i>Fashion</i></span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-targeting" data-demo-scene="6">
                                    <span class="demo-scene-header"><b>Market &amp; category targeting</b><small>Scope each layout</small></span>
                                    <span class="demo-targeting-shell">
                                        <span class="demo-target-controls"><b>Scope</b><span><i class="active">Global</i><i data-demo-cursor-target>By category</i></span><b>Category</b><span><i>All</i><i class="demo-target-fashion" data-demo-cursor-target>Fashion</i></span><b>Market</b><span><i>Global</i><i class="demo-target-market" data-demo-cursor-target>United States</i></span></span>
                                        <span class="demo-live-preview demo-target-preview"><b><i class="ti ti-eye"></i> Fashion · United States</b><span class="demo-storefront"><i class="hero"></i><i class="categories"></i><i class="products"></i></span></span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-scene demo-scene-media" data-demo-scene="7">
                                    <span class="demo-scene-header"><b>Media &amp; animations</b><small>Image, video &amp; Lottie</small></span>
                                    <span class="demo-media-shell">
                                        <span class="demo-live-preview demo-media-preview"><b><i class="ti ti-eye"></i> Live preview</b><span class="demo-media-canvas"><i class="ti ti-player-play-filled"></i><small>Summer collection</small><em></em></span></span>
                                        <span class="demo-media-controls"><b>Media type</b><span><i class="active">Image</i><i data-demo-cursor-target>Video</i><i data-demo-cursor-target>Lottie</i></span><b>Animation</b><span><i>None</i><i class="demo-media-fade" data-demo-cursor-target>Fade in</i></span><u><i class="ti ti-upload"></i> Upload media</u></span>
                                    </span>
                                </span>

                                <span class="dashboard-home-builder__demo-cursor"><i class="ti ti-pointer-filled"></i><small></small></span>
                            </span>
                        </span>

                        <span class="dashboard-home-builder__demo-footer">
                            <span class="dashboard-home-builder__demo-captions">
                                <span class="active" data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_app_web') }}</b><small>{{ __('labels.home_builder_demo_app_web_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_product') }}</b><small>{{ __('labels.home_builder_demo_product_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_play_image') }}</b><small>{{ __('labels.home_builder_demo_play_image_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_style') }}</b><small>{{ __('labels.home_builder_demo_style_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_navbar_create') }}</b><small>{{ __('labels.home_builder_demo_navbar_create_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_navbar_sort') }}</b><small>{{ __('labels.home_builder_demo_navbar_sort_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_targeting') }}</b><small>{{ __('labels.home_builder_demo_targeting_copy') }}</small></span>
                                <span data-home-builder-demo-caption><b>{{ __('labels.home_builder_demo_media') }}</b><small>{{ __('labels.home_builder_demo_media_copy') }}</small></span>
                            </span>
                            <span class="dashboard-home-builder__demo-progress" aria-hidden="true">
                                <i class="active"></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                            </span>
                        </span>
                    </a>
                    <a href="{{ route('admin.home-builder.index') }}" class="btn dashboard-home-builder__button">
                        {{ __('labels.open_home_builder') }}
                        <i class="ti ti-arrow-right"></i>
                    </a>
                </div>
            </section>
        @endif

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.revenue_and_gmv') }}</h2>
            <span class="badge bg-blue-lt ms-2">{{ __('labels.market_scoped') }}</span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader">{{ __('labels.gmv_ordered') }}
                            <x-form-info-tooltip :title="__('labels.tooltip_gmv_admin')" placement="top"/>
                        </div>
                        <div class="h1 mb-0 mt-1" id="gmv-total">{{ $gmvData['formatted_total'] ?? 0 }}</div>
                        <div class="text-secondary small mt-1">{{ $gmvData['days'] ?? 30 }} {{ __('labels.days') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader">{{ __('labels.net_commission') }}
                            <x-form-info-tooltip :title="__('labels.tooltip_net_commission')" placement="top"/>
                        </div>
                        <div class="h1 mb-0 mt-1" id="commission-total">{{ $enhancedCommissionsData['total_commission'] ?? 0 }}</div>
                        <div class="text-secondary small mt-1">{{ $enhancedCommissionsData['total_orders'] ?? 0 }} {{ __('labels.orders') }}</div>
                    </div>
                    <div id="chart-commission" class="chart-sm"></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader">{{ __('labels.avg_order_value') }}</div>
                        <div class="h1 mb-0 mt-1" id="aov-total">{{ $revenueVsOrders['formatted_aov'] ?? 0 }}</div>
                        <div class="text-secondary small mt-1">{{ $revenueVsOrders['total_orders'] ?? 0 }} {{ __('labels.orders') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader">{{ __('labels.revenue') }}</div>
                        <div class="h1 mb-0 mt-1" id="revenue-total">{{ $revenueVsOrders['formatted_total_revenue'] ?? 0 }}</div>
                        <div class="text-secondary small mt-1">{{ $revenueVsOrders['total_orders'] ?? 0 }} {{ __('labels.orders') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.revenue_vs_orders') }}</h3>
                        <div class="ms-auto">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-secondary revenue-orders-period" href="#"
                                    data-bs-toggle="dropdown" data-period="30">{{ __('labels.last_30_days') }}</a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                    <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body"><div id="chart-revenue-orders" style="min-height:280px;"></div></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.revenue_by_category') }}</h3></div>
                    <div class="card-body">
                        <div id="revenue-category-chart"></div>
                        <div id="revenue-category-empty" class="text-muted small text-center py-4 d-none">{{ __('labels.no_data') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.top_sellers') }}</h3></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('labels.seller') }}</th>
                                        <th class="text-center">{{ __('labels.orders') }}</th>
                                        <th class="text-end">{{ __('labels.revenue') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topSellers as $s)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-sm me-2" @if($s['avatar']) style="background-image: url('{{ $s['avatar'] }}')" @endif>
                                                        @unless($s['avatar']){{ mb_substr($s['name'], 0, 2) }}@endunless
                                                    </span>
                                                    <div>
                                                        <div class="fw-medium">{{ $s['name'] }}</div>
                                                        <div class="text-secondary small">{{ $s['email'] }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $s['total_orders'] }}</td>
                                            <td class="text-end fw-medium">{{ $s['total_revenue'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted py-3">{{ __('labels.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.order_health_and_returns') }}</h2>
            <span class="badge bg-blue-lt ms-2">{{ __('labels.market_scoped') }}</span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.fulfillment_rate') }}</h3></div>
                    <div class="card-body">
                        <div class="d-flex align-items-baseline">
                            <div class="h1 mb-0" id="fulfillment-rate">{{ $conversionRateData['rate'] ?? 0 }}%</div>
                            <div class="ms-2 text-{{ !empty($conversionRateData['is_increase']) ? 'green' : 'red' }} small">
                                {{ abs($conversionRateData['percentage_change'] ?? 0) }}%
                            </div>
                        </div>
                        <div class="text-secondary small mb-2">
                            {{ $conversionRateData['delivered_orders'] ?? 0 }}
                            {{ __('labels.delivered_out_of_total_orders') }}
                            {{ $conversionRateData['total_orders'] ?? 0 }}
                        </div>
                        <div class="progress progress-sm mb-3">
                            <div class="progress-bar bg-primary" style="width: {{ $conversionRateData['rate'] ?? 0 }}%"></div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h3 mb-0">{{ $orderFunnel['conversion_rate'] ?? 0 }}%</div>
                                <div class="text-secondary small">{{ __('labels.conversion_rate') }}</div>
                            </div>
                            <div class="col-6">
                                <div class="h3 mb-0 text-danger">{{ $orderFunnel['cancellation_rate'] ?? 0 }}%</div>
                                <div class="text-secondary small">{{ __('labels.cancellation_rate') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.order_funnel') }}</h3></div>
                    <div class="card-body"><div id="chart-order-funnel" style="min-height:230px;"></div></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.return_analytics') }}</h3></div>
                    <div class="card-body">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <div class="text-center p-2 rounded bg-danger-lt">
                                    <div class="h2 mb-0" id="ra-rate">{{ $returnAnalytics['return_rate'] }}%</div>
                                    <div class="text-muted small">{{ __('labels.return_rate') }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 rounded bg-warning-lt">
                                    <div class="h2 mb-0">{{ $returnAnalytics['total_refund'] }}</div>
                                    <div class="text-muted small">{{ __('labels.total_refunded') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="small text-muted">{{ __('labels.fault_split') }}</span>
                            <span class="ms-auto small">
                                <span class="text-danger fw-bold">{{ $returnAnalytics['seller_fault'] }} {{ __('labels.seller_fault') }}</span>
                                <span class="text-muted"> / </span>
                                <span class="text-info fw-bold">{{ $returnAnalytics['customer_fault'] }} {{ __('labels.customer_fault') }}</span>
                            </span>
                        </div>
                        <div class="progress progress-separated mb-3">
                            <div class="progress-bar bg-danger" style="width: {{ $returnAnalytics['seller_fault_share'] }}%"></div>
                            <div class="progress-bar bg-info" style="width: {{ $returnAnalytics['customer_fault_share'] }}%"></div>
                        </div>
                        <div class="text-muted small mb-1">{{ __('labels.top_return_sellers') }}</div>
                        @forelse($returnAnalytics['top_sellers'] as $s)
                            <div class="d-flex align-items-center mb-1">
                                <span class="small text-truncate">{{ $s['name'] }}</span>
                                <span class="ms-auto small">
                                    <span class="badge bg-danger-lt">{{ $s['returns_count'] }}</span>
                                    <span class="text-muted">{{ $s['refund_total'] }}</span>
                                </span>
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('labels.no_data') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.customers') }}</h2>
            <span class="badge bg-blue-lt ms-2">{{ __('labels.market_scoped') }}</span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.customer_insights') }}</h3>
                        <div class="ms-auto">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-secondary customer-insights-period" href="#"
                                    data-bs-toggle="dropdown" data-period="30">{{ __('labels.last_30_days') }}</a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#" data-period="7">{{ __('labels.last_7_days') }}</a>
                                    <a class="dropdown-item active" href="#" data-period="30">{{ __('labels.last_30_days') }}</a>
                                    <a class="dropdown-item" href="#" data-period="90">{{ __('labels.last_3_months') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="customer-insights-container">
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <div class="text-center p-2 rounded bg-primary-lt">
                                    <div class="h2 mb-0" id="ci-repeat-rate">{{ $customerInsights['repeat_rate'] }}%</div>
                                    <div class="text-muted small">{{ __('labels.repeat_rate') }}
                                        <x-form-info-tooltip :title="__('labels.tooltip_repeat_rate')" placement="top"/>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-center p-2 rounded bg-success-lt">
                                    <div class="h2 mb-0" id="ci-avg-basket">{{ $customerInsights['avg_basket_size'] }}</div>
                                    <div class="text-muted small">{{ __('labels.avg_basket_size') }}</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-center p-2 rounded bg-danger-lt">
                                    <div class="h2 mb-0" id="ci-churn-risk">{{ $customerInsights['churn_risk'] }}</div>
                                    <div class="text-muted small">{{ __('labels.churn_risk') }}
                                        <x-form-info-tooltip :title="__('labels.tooltip_churn_risk')" placement="top"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary badge-empty me-2"></span>
                                    <span class="small">{{ __('labels.new_customers') }}</span>
                                    <span class="ms-auto fw-bold" id="ci-new">{{ $customerInsights['new_customers'] }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success badge-empty me-2"></span>
                                    <span class="small">{{ __('labels.returning_customers') }}</span>
                                    <span class="ms-auto fw-bold" id="ci-returning">{{ $customerInsights['returning_customers'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-1">
                            {{ __('labels.total_active_customers') }}: <strong id="ci-total">{{ $customerInsights['total_customers'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('labels.revenue_new_vs_returning') }}</h3></div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="text-primary fw-bold" id="ci-new-rev">{{ $customerInsights['new_revenue'] }}</span>
                            <span class="ms-auto text-success fw-bold" id="ci-returning-rev">{{ $customerInsights['returning_revenue'] }}</span>
                        </div>
                        <div class="progress progress-separated mb-2" style="height: 1.5rem;">
                            <div class="progress-bar bg-primary" id="ci-new-rev-bar" style="width: {{ $customerInsights['new_revenue_share'] }}%"></div>
                            <div class="progress-bar bg-success" id="ci-returning-rev-bar" style="width: {{ $customerInsights['returning_revenue_share'] }}%"></div>
                        </div>
                        <div class="row text-muted small">
                            <div class="col-6"><span class="badge bg-primary badge-empty me-1"></span>{{ __('labels.new_customers') }} <span id="ci-new-rev-share">{{ $customerInsights['new_revenue_share'] }}</span>%</div>
                            <div class="col-6 text-end">{{ __('labels.returning_customers') }} <span id="ci-returning-rev-share">{{ $customerInsights['returning_revenue_share'] }}</span>% <span class="badge bg-success badge-empty ms-1"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2 d-flex align-items-center">
            <h2 class="page-title mb-0">{{ __('labels.money_catalog_inventory') }}</h2>
            <span class="badge bg-secondary-lt ms-2">{{ __('labels.global_not_scoped') }}
                <x-form-info-tooltip :title="__('labels.tooltip_global_not_scoped')" placement="top"/>
            </span>
        </div>
        <div class="row row-deck row-cards mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.unsettled_earnings') }}</div>
                    <div class="h1 mb-0 mt-1">{{ $sellerSettlements['total_outstanding'] ?? 0 }}</div>
                    <div class="text-secondary small mt-1">{{ $sellerSettlements['pending_count'] ?? 0 }} {{ __('labels.pending') }}</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.pending_withdrawals') }}</div>
                    <div class="h1 mb-0 mt-1">{{ $withdrawals['seller_pending'] ?? 0 }}</div>
                    <div class="text-secondary small mt-1">{{ __('labels.oldest') }}: {{ $withdrawals['oldest_pending_days'] ?? 0 }} {{ __('labels.days') }}</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.pending_deposits') }}</div>
                    <div class="h1 mb-0 mt-1">{{ $globalOps['pending_deposits'] ?? 0 }}</div>
                    <div class="text-secondary small mt-1">{{ $globalOps['pending_deposits_amount'] ?? 0 }}</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">{{ __('labels.stock_alerts') }}
                        <x-form-info-tooltip :title="__('labels.tooltip_stock_alerts')" placement="top"/>
                    </div>
                    <div class="h1 mb-0 mt-1">
                        <span class="text-warning">{{ $globalOps['low_stock'] ?? 0 }}</span>
                        <span class="text-secondary h3">/</span>
                        <span class="text-danger">{{ $globalOps['out_of_stock'] ?? 0 }}</span>
                    </div>
                    <div class="text-secondary small mt-1">{{ __('labels.low') }} / {{ __('labels.out_of_stock') }}</div>
                </div></div>
            </div>

            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <div class="card-title">{{ __('labels.catalog_moderation') }}</div>
                    <div class="row g-2 mb-3 mt-1">
                        <div class="col-4"><div class="text-center p-2 rounded bg-success-lt">
                            <div class="h2 mb-0">{{ $catalogModeration['approved'] }}</div>
                            <div class="text-muted small">{{ __('labels.approved') }}</div>
                        </div></div>
                        <div class="col-4"><div class="text-center p-2 rounded bg-warning-lt">
                            <div class="h2 mb-0">{{ $catalogModeration['pending'] }}</div>
                            <div class="text-muted small">{{ __('labels.pending') }}</div>
                        </div></div>
                        <div class="col-4"><div class="text-center p-2 rounded bg-danger-lt">
                            <div class="h2 mb-0">{{ $catalogModeration['rejected'] }}</div>
                            <div class="text-muted small">{{ __('labels.rejected') }}</div>
                        </div></div>
                    </div>
                    <div class="d-flex align-items-center mb-1">
                        <span class="small text-muted">{{ __('labels.approval_rate') }}</span>
                        <span class="ms-auto fw-bold text-success">{{ $catalogModeration['approval_rate'] }}%</span>
                    </div>
                    <div class="progress progress-separated">
                        <div class="progress-bar bg-success" style="width: {{ $catalogModeration['approval_rate'] }}%"></div>
                        <div class="progress-bar bg-danger" style="width: {{ $catalogModeration['rejection_rate'] }}%"></div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <div class="card-title">{{ __('labels.settlements') }}</div>
                    <div class="row g-2 mt-1">
                        <div class="col-6"><div class="p-2 rounded bg-primary-lt">
                            <div class="text-muted small">{{ __('labels.unsettled_earnings') }}</div>
                            <div class="h3 mb-0">{{ $sellerSettlements['pending_amount'] ?? 0 }}</div>
                        </div></div>
                        <div class="col-6"><div class="p-2 rounded bg-success-lt">
                            <div class="text-muted small">{{ __('labels.settled') }}</div>
                            <div class="h3 mb-0">{{ $sellerSettlements['settled_amount'] ?? 0 }}</div>
                        </div></div>
                        <div class="col-6"><div class="p-2 rounded bg-secondary-lt">
                            <div class="text-muted small">{{ __('labels.withdrawals_approved') }}</div>
                            <div class="h3 mb-0">{{ $withdrawals['approved_amount'] ?? 0 }}</div>
                        </div></div>
                        <div class="col-6"><div class="p-2 rounded bg-secondary-lt">
                            <div class="text-muted small">{{ __('labels.withdrawals_rejected') }}</div>
                            <div class="h3 mb-0">{{ $withdrawals['total_rejected'] ?? 0 }}</div>
                        </div></div>
                    </div>
                </div></div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{asset('assets/vendor/apexcharts/dist/apexcharts.min.js')}}" defer></script>
    <script>
        var dashboardData = {
            revenueVsOrders: @json($revenueVsOrders),
            commissionDaily: @json($enhancedCommissionsData['daily_data'] ?? []),
            revenueByCategory: @json($revenueByCategory),
            orderFunnel: @json($orderFunnel),
            returnAnalytics: @json($returnAnalytics)
        };
        var currentMarketId = @json($marketId ?? null);
        var dashboardDataUrl = "{{ route('admin.dashboard.data') }}";
    </script>
    <script src="{{hyperAsset('assets/js/admin-dashboard.js')}}" defer></script>
@endpush
