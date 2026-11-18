@extends('layouts.admin.app', ['page' => $menuAdmin['landing_page_builder']['active'] ?? 'landing-page-builder'])

@section('title', __('labels.landing_page_builder'))

@section('header_data')
    @php
        $page_title = __('labels.landing_page_builder');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.landing_page_builder'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.landing_page_builder') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions d-flex gap-2">
                        @if ($canEdit)
                            <a href="{{ route('admin.landing-page-builder.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus icon" aria-hidden="true"></i>{{ __('labels.create_landing_page') }}
                            </a>
                        @endif
                        <button type="button" class="btn btn-outline-primary" id="refresh-landing-pages">
                            <i class="ti ti-refresh icon" aria-hidden="true"></i>{{ __('labels.refresh') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-secondary mb-0">{{ __('labels.landing_page_builder_description') }}</p>
                </div>
                <div class="card-table">
                    <div class="row g-3 p-3">
                        <x-datatable id="landing-pages-table" :columns="$columns"
                                     route="{{ route('admin.landing-page-builder.datatable') }}"
                                     :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.landingPageBuilderLabels = {
            deleteConfirmation: @json(__('labels.landing_page_delete_confirmation')),
            linkCopied: @json(__('labels.landing_page_link_copied')),
            copyFailed: @json(__('labels.landing_page_link_copy_failed')),
            shareOpened: @json(__('labels.landing_page_share_opened')),
            confirmationUnavailable: @json(__('labels.confirmation_unavailable')),
            delete: @json(__('labels.delete')),
            cancel: @json(__('labels.cancel')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/landing-page-builder.js') }}" defer></script>
@endpush
