@extends('layouts.'.$panelName.'.app', ['page' => ($panelName === 'admin' ? $menuAdmin : $menuSeller)[$moduleConfig->menuKey]['active'] ?? '', 'sub_page' => ($panelName === 'admin' ? $menuAdmin : $menuSeller)[$moduleConfig->menuKey]['route']['bulk_upload']['sub_active'] ?? null])

@php
    $moduleDisplay = __($moduleConfig->displayNameKey);
@endphp

@section('title', __('labels.bulk_upload_history') . ' — ' . $moduleDisplay)

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload_history');
        $page_pretitle = $moduleDisplay;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route($panelName.'.dashboard')],
        ['title' => $moduleDisplay, 'url' => route($panelName.'.'.$moduleConfig->key.'.index')],
        ['title' => __('labels.bulk_upload_history'), 'url' => null],
    ];
@endphp

@section($panelName.'-content')
    <div class="row row-cards">
        <div class="col-12">
            <x-bulk-upload.jobs-table
                :module-config="$moduleConfig"
                :columns="$columns"
                :breadcrumbs="$breadcrumbs"
                :can-create="$canCreate"
                :panel-name="$panelName"
            />
        </div>
    </div>
@endsection
