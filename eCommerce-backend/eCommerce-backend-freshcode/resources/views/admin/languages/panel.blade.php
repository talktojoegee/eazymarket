@extends('layouts.admin.app', ['page' => $menuAdmin['languages']['active'] ?? '', 'sub_page' => $menuAdmin['languages']['route']['panel']['sub_active'] ?? ''])

@section('title', __('labels.panel_languages'))

@section('header_data')
    @php
        $page_title = __('labels.panel_languages');
        $page_pretitle = __('labels.languages_management');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.languages_management'), 'url' => route('admin.languages.index')],
        ['title' => __('labels.panel_languages'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.panel_languages') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                        <div class="text-muted">{{ __('labels.panel_languages_description') }}</div>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            @if($createPermission)
                                <div class="col-auto">
                                    <a href="{{ route('admin.languages.create') }}" class="btn btn-6 btn-outline-primary">
                                        <i class="ti ti-plus"></i>
                                        {{ __('labels.add_language') }}
                                    </a>
                                </div>
                            @endif
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh" type="button">
                                    <i class="ti ti-refresh"></i>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="card-actions mt-3 ms-3">
                        <div class="row g-2">
                            <div class="col-auto">
                                <label class="visually-hidden" for="language-status-filter">{{ __('labels.status_filter') }}</label>
                                <select id="language-status-filter" class="form-select">
                                    <option value="">{{ __('labels.all_statuses') }}</option>
                                    <option value="active">{{ __('labels.active') }}</option>
                                    <option value="inactive">{{ __('labels.inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row w-full p-3">
                        <x-datatable
                            id="languages-table"
                            :columns="$columns"
                            route="{{ route('admin.languages.datatable') }}"
                            :options="['order' => [[6, 'asc']], 'pageLength' => 10]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            @include('admin.languages.partials.quick-add-languages')
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.languageLabels = {
            areYouSure: @json(__('labels.are_you_sure')),
            cancel: @json(__('labels.cancel')),
            confirmDeactivate: @json(__('labels.language_deactivate_confirm')),
            deactivate: @json(__('labels.deactivate')),
            requestFailed: @json(__('labels.language_action_failed')),
            deleteConfirm: @json(__('labels.language_delete_confirm')),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/languages.js') }}" defer></script>
@endpush
