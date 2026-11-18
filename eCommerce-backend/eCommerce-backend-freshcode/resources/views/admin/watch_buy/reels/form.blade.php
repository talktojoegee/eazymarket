@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['reels']['sub_active'] ?? '',
])

@section('title', $reel->exists ? __('labels.watch_buy_edit_reel_page') : __('labels.watch_buy_add_reel_page'))

@section('header_data')
    @php $page_title = $reel->exists ? __('labels.watch_buy_edit_reel_page') : __('labels.watch_buy_add_reel_page'); @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => route('admin.watch-and-buy.reels.index')],
        ['title' => $reel->exists ? __('labels.watch_buy_edit_reel') : __('labels.watch_buy_add_reel'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <form class="form-submit" method="post" enctype="multipart/form-data" action="{{ $reel->exists ? route('admin.watch-and-buy.reels.update', $reel) : route('admin.watch-and-buy.reels.store') }}" data-redirect="{{ route('admin.watch-and-buy.reels.index') }}">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ $reel->exists ? __('labels.watch_buy_edit_reel') : __('labels.watch_buy_add_reel') }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.watch_buy_creation_mode') }}</label>
                                            <select class="form-select" name="source" id="watch-buy-source" required>
                                                <option value="seller_behalf" @selected(old('source', $reel->source ?: 'seller_behalf') === 'seller_behalf')>{{ __('labels.watch_buy_seller_behalf') }}</option>
                                                <option value="admin_direct" @selected(old('source', $reel->source) === 'admin_direct')>{{ __('labels.watch_buy_admin_direct') }}</option>
                                            </select>
                                            @error('source')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="watch-buy-profile-field">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.watch_buy_seller_profile') }}</label>
                                            <select class="form-select js-tom-select-remote" name="profile_id" id="watch-buy-profile"
                                                    data-endpoint="{{ route('admin.watch-and-buy.profiles.options') }}"
                                                    data-selected='@json(array_filter([(int) old('profile_id', $reel->profile_id)]))'
                                                    data-placeholder="{{ __('labels.watch_buy_search_seller_profiles') }}"
                                                    data-preload="focus"
                                                    data-preload-limit="15"
                                                    data-image-style="1">
                                                @if($selectedProfile)
                                                    <option value="{{ $selectedProfile->id }}" selected>{{ '@'.$selectedProfile->username }} — {{ $selectedProfile->seller?->user?->name }}</option>
                                                @endif
                                            </select>
                                            @error('profile_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </div>
                                @include('watch_buy._reel_fields', [
                                    'productSourceSelector' => '#watch-buy-source',
                                    'productProfileSelector' => '#watch-buy-profile',
                                ])
                            </div>
                            <div class="col-lg-4">
                                @include('admin.watch_buy.reels._mobile-preview', [
                                    'videoUrl' => $reel->video_url,
                                    'coverUrl' => $reel->cover_url,
                                    'profileName' => $selectedProfile
                                        ? '@'.$selectedProfile->username
                                        : (old('source', $reel->source ?: 'seller_behalf') === 'admin_direct'
                                            ? __('labels.watch_buy_official_hypercommerce_profile')
                                            : __('labels.watch_buy_seller_profile')),
                                ])
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex">
                        <a href="{{ route('admin.watch-and-buy.reels.index') }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
                        <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.watch_buy_save_reel') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/watch-buy-reel-form.js') }}" defer></script>
@endpush

@push('styles')
    <link href="{{ hyperAsset('assets/css/watch-buy-reel-form.css') }}" rel="stylesheet"/>
@endpush
