@php
    $rt = fn ($name, $params = []) => route(($panel === 'admin' ? 'admin.sellers.store.' : 'seller.stores.') . $name, $params);
    $shiprocketEnabled = (bool) (app(\App\Services\SettingService::class)->shiprocketConfig()['shiprocketEnabled'] ?? false);
@endphp

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.locations') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
            @if ($createPermission)
                <div class="col-auto">
                    <a href="{{ $rt('locations.create', ['storeId' => $store->id]) }}"
                       class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_location') }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="page-body">
        @if ($locations->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-map-pin"></i></div>
                <p class="empty-title">{{ __('labels.no_locations_yet') }}</p>
            </div>
        @else
            <div class="row row-cards">
                @foreach ($locations as $location)
                    <div class="col-md-6 col-lg-4">
                        <div class="card location-card" data-location-id="{{ $location->id }}">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <h3 class="mb-1">{{ $location->name }} <span class="badge bg-secondary-lt user-select-all">#{{ $location->id }}</span></h3>
                                        <div class="text-secondary small">
                                            {{ collect([$location->city, $location->state, $location->country_name])->filter()->join(', ') ?: __('labels.address') }}
                                        </div>
                                    </div>
                                    @if ($location->is_default)
                                        <span class="badge bg-blue-lt border border-blue-subtle">
                                            <i class="ti ti-star-filled me-1"></i>{{ __('labels.default_location_badge') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="d-flex flex-wrap gap-1 my-2">
                                    @if ($location->fulfills_online_orders)
                                        <span class="badge bg-green-lt">{{ __('labels.fulfills_online_orders') }}</span>
                                    @endif
                                    @if ($location->is_physical_storefront)
                                        <span class="badge bg-cyan-lt">{{ __('labels.is_physical_storefront') }}</span>
                                    @endif
                                    @if ($location->offers_local_delivery)
                                        <span class="badge bg-orange-lt">{{ __('labels.offers_local_delivery') }}</span>
                                    @endif
                                    @if ($location->offers_local_pickup)
                                        <span class="badge bg-yellow-lt">{{ __('labels.offers_local_pickup') }}</span>
                                    @endif
                                    @if ($location->offers_pos)
                                        <span class="badge bg-purple-lt">{{ __('labels.offers_pos') }}</span>
                                    @endif
                                    @if (! $location->is_active)
                                        <span class="badge bg-red-lt">{{ __('labels.inactive') }}</span>
                                    @endif
                                </div>

                                @if ($shiprocketEnabled && $location->fulfills_online_orders)
                                    <div class="mt-2">
                                        @php $srStatus = $location->shiprocket_sync_status; @endphp
                                        @if ($srStatus === 'registered')
                                            <span class="badge bg-green-lt" data-bs-toggle="tooltip"
                                                  title="{{ __('labels.shiprocket_status_registered_hint') }}">
                                                <i class="ti ti-truck-delivery me-1"></i>{{ __('labels.shiprocket_status_registered') }}
                                            </span>
                                        @elseif ($srStatus === 'pending')
                                            <span class="badge bg-yellow-lt">
                                                <i class="ti ti-loader-2 me-1"></i>{{ __('labels.shiprocket_status_pending') }}
                                            </span>
                                        @elseif ($srStatus === 'failed')
                                            <span class="badge bg-red-lt" data-bs-toggle="tooltip"
                                                  title="{{ $location->shiprocket_sync_error ?? __('labels.shiprocket_status_failed') }}">
                                                <i class="ti ti-alert-triangle me-1"></i>{{ __('labels.shiprocket_status_failed') }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-lt">
                                                <i class="ti ti-truck-off me-1"></i>{{ __('labels.shiprocket_status_none') }}
                                            </span>
                                        @endif

                                        @if ($editPermission && $srStatus !== 'pending')
                                            <button type="button"
                                                    class="btn btn-ghost-secondary btn-sm py-0 px-1 ms-1 js-shiprocket-sync"
                                                    data-action="{{ $rt('locations.shiprocket-sync', ['storeId' => $store->id, 'locationId' => $location->id]) }}"
                                                    data-bs-toggle="tooltip"
                                                    title="{{ $srStatus === 'registered' ? __('labels.shiprocket_resync') : __('labels.shiprocket_register') }}">
                                                <i class="ti ti-refresh"></i>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="card-footer d-flex gap-2">
                                @if ($editPermission)
                                    <a href="{{ $rt('locations.edit', ['storeId' => $store->id, 'locationId' => $location->id]) }}"
                                       class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="ti ti-pencil me-1"></i>{{ __('labels.edit') }}
                                    </a>
                                @endif
                                @if ($editPermission && ! $location->is_default)
                                    <button type="button"
                                            class="btn btn-sm js-promote-default"
                                            data-action="{{ $rt('locations.promote-default', ['storeId' => $store->id, 'locationId' => $location->id]) }}"
                                            data-location-name="{{ $location->name }}">
                                        <i class="ti ti-star me-1"></i>{{ __('labels.promote_to_default') }}
                                    </button>
                                @endif
                                @if ($deletePermission && ! $location->is_default)
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm js-delete-location"
                                            data-action="{{ $rt('locations.delete', ['storeId' => $store->id, 'locationId' => $location->id]) }}"
                                            data-location-name="{{ $location->name }}">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@include('seller.store_locations.partials._promote-default-modal')
@include('seller.store_locations.partials._delete-location-modal')

@push('scripts')
    <script src="{{ hyperAsset('assets/js/store-locations-index.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                if (window.bootstrap && !bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el);
                }
            });

            document.querySelectorAll('.js-shiprocket-sync').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    btn.disabled = true;
                    btn.querySelector('i').className = 'ti ti-loader-2';
                    try {
                        const res = await axios.post(btn.dataset.action);
                        Toast.fire({
                            icon: res.data?.success ? 'success' : 'error',
                            title: res.data?.message ?? @json(__('labels.something_went_wrong')),
                        });
                        setTimeout(function () { window.location.reload(); }, 1200);
                        return;
                    } catch (e) {
                        Toast.fire({
                            icon: 'error',
                            title: e.response?.data?.message ?? @json(__('labels.something_went_wrong')),
                        });
                    }
                    btn.disabled = false;
                    btn.querySelector('i').className = 'ti ti-refresh';
                });
            });
        });
    </script>
@endpush
