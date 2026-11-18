@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['profiles']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_profiles'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_profiles');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_profiles'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.watch_buy_profiles') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            @if($editPermission)
                                <div class="col-auto">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#watch-buy-profile-modal">
                                        <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_seller_profile') }}
                                    </button>
                                </div>
                            @endif
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom py-3" data-watch-buy-filters data-table-id="watch-buy-profiles-table">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label mb-1">{{ __('labels.status') }}</label>
                            <select class="form-select js-tom-select-static" data-watch-buy-filter="status" data-placeholder="{{ __('labels.all') }}">
                                <option value="">{{ __('labels.all') }}</option>
                                @foreach(['active', 'suspended'] as $status)
                                    <option value="{{ $status }}">{{ Str::headline($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn w-100" data-watch-buy-filter-reset>{{ __('labels.reset') }}</button>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="watch-buy-profiles-table" :columns="$columns"
                                     route="{{ route('admin.watch-and-buy.profiles.datatable') }}"
                                     :options="['order' => [[5, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($editPermission)
        <div class="modal modal-blur fade" id="watch-buy-profile-modal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form class="form-submit" method="post" enctype="multipart/form-data" action="{{ route('admin.watch-and-buy.profiles.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('labels.watch_buy_create_seller_profile') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.seller') }}</label>
                                <select class="form-select js-tom-select-remote" name="seller_id" required
                                        data-endpoint="{{ route('admin.watch-and-buy.profiles.seller-options') }}"
                                        data-selected='@json(array_filter([(int) old('seller_id')]))'
                                        data-placeholder="{{ __('labels.watch_buy_search_sellers') }}"
                                        data-preload="focus"
                                        data-preload-limit="15"></select>
                                @error('seller_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            @include('watch_buy._profile_fields', ['profile' => null])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('labels.watch_buy_create_profile') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-management.js') }}" defer></script>
    @if($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('watch-buy-profile-modal')).show();
            });
        </script>
    @endif
@endpush
