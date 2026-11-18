@extends('layouts.admin.app', ['page' => $menuAdmin['store_locations']['active'] ?? ''])

@section('title', __('labels.store_locations'))

@section('header_data')
    @php
        $page_title = __('labels.store_locations');
        $page_pretitle = __('labels.admin');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.store_locations'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.store_locations') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="card">
                <div class="card-body border-bottom py-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">{{ __('labels.search') }}</label>
                            <input type="text" name="q" value="{{ $search }}" class="form-control"
                                   placeholder="{{ __('labels.store') }} / {{ __('labels.city') }} / {{ __('labels.pin_code') }}">
                        </div>
                        @if ($shiprocketEnabled)
                            <div class="col-md-4">
                                <label class="form-label">{{ __('labels.shiprocket') }}</label>
                                <select name="sync_status" class="form-select">
                                    <option value="">{{ __('labels.all') }}</option>
                                    @foreach (['registered', 'pending', 'failed'] as $s)
                                        <option value="{{ $s }}" @selected($status === $s)>{{ __('labels.shiprocket_status_' . $s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-filter me-1"></i>{{ __('labels.filter') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>{{ __('labels.id') }}</th>
                                <th>{{ __('labels.store') }}</th>
                                <th>{{ __('labels.seller') }}</th>
                                <th>{{ __('labels.location') }}</th>
                                <th>{{ __('labels.address') }}</th>
                                @if ($shiprocketEnabled)
                                    <th>{{ __('labels.shiprocket') }}</th>
                                    <th class="w-1"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($locations as $location)
                                <tr>
                                    <td><span class="badge bg-secondary-lt user-select-all">#{{ $location->id }}</span></td>
                                    <td>{{ $location->store->name ?? '—' }}</td>
                                    <td>{{ $location->store->seller->user->name ?? '—' }}</td>
                                    <td>
                                        {{ $location->name }}
                                        @if ($location->is_default)
                                            <span class="badge bg-blue-lt ms-1">{{ __('labels.default_location_badge') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-secondary">
                                        {{ collect([$location->address_1, $location->city, $location->state, $location->pin_code, $location->country_name])->filter()->join(', ') ?: '—' }}
                                    </td>
                                    @if ($shiprocketEnabled)
                                        <td>
                                            @php $srStatus = $location->shiprocket_sync_status; @endphp
                                            @if ($srStatus === 'registered')
                                                <span class="badge bg-green-lt">{{ __('labels.shiprocket_status_registered') }}</span>
                                            @elseif ($srStatus === 'pending')
                                                <span class="badge bg-yellow-lt">{{ __('labels.shiprocket_status_pending') }}</span>
                                            @elseif ($srStatus === 'failed')
                                                <span class="badge bg-red-lt" data-bs-toggle="tooltip"
                                                      title="{{ $location->shiprocket_sync_error ?? __('labels.shiprocket_status_failed') }}">{{ __('labels.shiprocket_status_failed') }}</span>
                                            @else
                                                <span class="badge bg-secondary-lt">{{ __('labels.shiprocket_status_none') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($location->fulfills_online_orders && $srStatus !== 'pending')
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary js-shiprocket-sync"
                                                        data-action="{{ route('admin.sellers.store.locations.shiprocket-sync', ['storeId' => $location->store_id, 'locationId' => $location->id]) }}"
                                                        data-bs-toggle="tooltip"
                                                        title="{{ $srStatus === 'registered' ? __('labels.shiprocket_resync') : __('labels.shiprocket_register') }}">
                                                    <i class="ti ti-refresh"></i>
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $shiprocketEnabled ? 6 : 4 }}" class="text-center text-secondary py-4">
                                        {{ __('labels.no_locations_yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex align-items-center">
                    {{ $locations->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
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
                        setTimeout(function () { window.location.reload(); }, 3000);
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
