@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_content']['route']['profiles']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_edit_profile'))

@section('header_data')
    @php $page_title = __('labels.watch_buy_edit_profile'); @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.watch_buy_profiles'), 'url' => route('admin.watch-and-buy.profiles.index')],
        ['title' => '@'.$profile->username, 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <form class="form-submit" method="post" enctype="multipart/form-data" action="{{ route('admin.watch-and-buy.profiles.update', $profile) }}" data-redirect="{{ route('admin.watch-and-buy.profiles.index') }}">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('labels.watch_buy_edit_username', ['username' => '@'.$profile->username]) }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('watch_buy._profile_fields', ['profile' => $profile])
                    </div>
                    <div class="card-footer d-flex">
                        <a href="{{ route('admin.watch-and-buy.profiles.index') }}" class="btn btn-link">{{ __('labels.cancel') }}</a>
                        <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.watch_buy_save_profile') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
