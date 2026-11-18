@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['shipping']['sub_active'] ?? ""])

@section('title', __('labels.manual_shipping'))

@section('header_data')
    @php
        $page_title = __('labels.manual_shipping');
        $page_pretitle = __('labels.shipping_settings') ?? 'Shipping Settings';
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.shipping_settings') ?? 'Shipping Settings', 'url' => route('admin.settings.shipping.index')],
        ['title' => __('labels.manual_shipping'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title"><i class="ti ti-truck me-1"></i>{{ __('labels.manual_shipping') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                @csrf
                <input type="hidden" name="type" value="shipping">

                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title"><i class="ti ti-truck me-1"></i>{{ __('labels.manual_shipping') }}</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary">{{ __('labels.manual_provider_desc') }}</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-check form-switch">
                                    <input type="hidden" name="manualEnabled" value="0">
                                    <input class="form-check-input" type="checkbox" name="manualEnabled" value="1"
                                        @checked($settings['manualEnabled'] ?? true)>
                                    <span class="form-check-label">{{ __('labels.enable_manual_shipping') }}
                                        <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                           data-bs-toggle="tooltip"
                                           title="{{ __('labels.tip_enable_manual_shipping') }}"></i>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.display_title') ?? 'Display title (shown to sellers)' }}
                                <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                   data-bs-toggle="tooltip" title="{{ __('labels.tip_manual_title') }}"></i>
                            </label>
                            <input type="text" class="form-control" name="manualTitle" maxlength="60"
                                   placeholder="Standard Shipping" value="{{ $settings['manualTitle'] ?? 'Standard Shipping' }}">
                            <small class="text-secondary">{{ __('labels.manual_title_hint') }}</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title"><i class="ti ti-clock-exclamation me-1"></i>{{ __('labels.dispatch_sla') }}</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ __('labels.dispatch_sla_intro') }}</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('labels.dispatch_sla_days') }}
                                    <i class="ti ti-help-circle text-secondary ms-1" style="cursor:help"
                                       data-bs-toggle="tooltip" title="{{ __('labels.tip_dispatch_sla_days') }}"></i>
                                </label>
                                <input type="number" min="0" max="60" step="1" class="form-control"
                                       name="manualDispatchSlaDays"
                                       value="{{ $settings['manualDispatchSlaDays'] ?? 0 }}">
                                <small class="text-secondary">{{ __('labels.dispatch_sla_days_hint') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-end">
                    <div class="d-flex">
                        @can('updateSetting', [\App\Models\Setting::class, 'shipping'])
                            <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                        @endcan
                    </div>
                </div>
            </form>
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
        });
    </script>
@endpush
