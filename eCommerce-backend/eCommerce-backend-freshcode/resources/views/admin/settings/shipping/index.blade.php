@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['shipping']['sub_active'] ?? ""])

@section('title', __('labels.shipping_settings') ?? 'Shipping Settings')

@section('header_data')
    @php
        $page_title = __('labels.shipping_settings') ?? 'Shipping Settings';
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.shipping_settings') ?? 'Shipping Settings', 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.shipping_settings') ?? 'Shipping Settings' }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <p class="text-secondary">{{ __('labels.shipping_providers_hint') }}</p>
            <div class="row row-cards">
                @foreach ($providers as $provider)
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div class="d-flex align-items-center">
                                        @if (!empty($provider['logo']))
                                            <img src="{{ asset($provider['logo']) }}" alt="" class="avatar avatar-md me-3">
                                        @else
                                            <span class="avatar avatar-md bg-primary-lt me-3">
                                                <i class="ti {{ $provider['icon'] }} fs-2"></i>
                                            </span>
                                        @endif
                                        <div>
                                            <h3 class="mb-0">{{ $provider['name'] }}</h3>
                                            @if (!empty($provider['coming_soon']))
                                                <span class="badge bg-yellow-lt">{{ __('labels.coming_soon') }}</span>
                                            @elseif ($provider['enabled'])
                                                <span class="badge bg-green-lt">{{ __('labels.enabled') }}</span>
                                            @else
                                                <span class="badge bg-secondary-lt">{{ __('labels.disabled') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <p class="text-secondary">{{ $provider['description'] }}</p>
                            </div>
                            <div class="card-footer">
                                @if (!empty($provider['coming_soon']))
                                    <button type="button" class="btn w-100" disabled>
                                        <i class="ti ti-clock-pause me-1"></i>{{ __('labels.coming_soon') }}
                                    </button>
                                @else
                                    <a href="{{ $provider['url'] }}" class="btn btn-primary w-100">
                                        <i class="ti ti-settings me-1"></i>{{ __('labels.configure') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
