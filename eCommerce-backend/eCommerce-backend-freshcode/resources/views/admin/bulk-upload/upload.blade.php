@extends('layouts.'.$panelName.'.app', ['page' => ($panelName === 'admin' ? $menuAdmin : $menuSeller)[$moduleConfig->menuKey]['active'] ?? '', 'sub_page' => ($panelName === 'admin' ? $menuAdmin : $menuSeller)[$moduleConfig->menuKey]['route']['bulk_upload']['sub_active'] ?? null])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
@endphp

@section('title', __('labels.bulk_upload') . ' ' . $moduleDisplay)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload') . ' ' . $moduleDisplay;
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route($panelName.'.dashboard')],
        ['title' => $moduleDisplay, 'url' => route($panelName.'.'.$moduleConfig->key.'.index')],
        ['title' => __('labels.bulk_upload'), 'url' => null],
    ];
@endphp

@section($panelName.'-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.bulk_upload') }} {{ $moduleDisplay }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions d-flex gap-2">
                        <a href="{{ route($panelName.'.'.$moduleConfig->key.'.bulk-upload.jobs.index') }}" class="btn btn-outline-primary">
                            {{ __('labels.bulk_upload_history') }}
                        </a>
                        <a href="{{ route($panelName.'.'.$moduleConfig->key.'.index') }}" class="btn btn-outline-secondary">
                            {{ __('labels.back') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <x-bulk-upload.upload-form
                        :module-config="$moduleConfig"
                        :available-fields="$availableFields"
                        :available-match-fields="$availableMatchFields"
                        :active-job="$activeJob"
                        :panel-name="$panelName"
                        :seller-id="$sellerId"
                        :sellers="$sellers"
                        :can-export="$canExport"
                        :currency-code="$currencyCode"
                        :currency-symbol="$currencySymbol"
                    />
                </div>
            </div>
        </div>
    </div>
@endsection
