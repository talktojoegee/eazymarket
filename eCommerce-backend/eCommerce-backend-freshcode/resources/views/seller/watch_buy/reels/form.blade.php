@extends('layouts.seller.app', [
    'page' => $menuSeller['watch_and_buy']['active'] ?? '',
    'sub_page' => $menuSeller['watch_and_buy']['route']['reels']['sub_active'] ?? '',
])

@section('title', $reel->exists ? __('labels.watch_buy_edit_reel_page') : __('labels.watch_buy_add_reel_page'))

@section('header_data')
    @php $page_title = $reel->exists ? __('labels.watch_buy_edit_reel_page') : __('labels.watch_buy_add_reel_page'); @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.watch_buy_reels'), 'url' => route('seller.watch-and-buy.reels.index')],
        ['title' => $reel->exists ? __('labels.watch_buy_edit_reel') : __('labels.watch_buy_add_reel'), 'url' => null],
    ];
@endphp

@section('seller-content')
    <div class="row row-cards">
        <div class="col-12">
            <form class="form-submit" method="post" enctype="multipart/form-data" action="{{ $reel->exists ? route('seller.watch-and-buy.reels.update', $reel) : route('seller.watch-and-buy.reels.store') }}" data-redirect="{{ route('seller.watch-and-buy.reels.index') }}">
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
                                @include('watch_buy._reel_fields')
                            </div>
                            <div class="col-lg-4">
                                @include('admin.watch_buy.reels._mobile-preview', [
                                    'videoUrl' => $reel->video_url,
                                    'coverUrl' => $reel->cover_url,
                                    'profileName' => '@'.$profile->username,
                                    'profilePhotoUrl' => $profile->photo_url,
                                ])
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex">
                        <a href="{{ route('seller.watch-and-buy.reels.index') }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
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
