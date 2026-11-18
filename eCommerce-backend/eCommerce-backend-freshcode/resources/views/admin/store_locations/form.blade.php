@extends('layouts.admin.app', ['page' => $menuAdmin['stores']['active'] ?? ''])

@section('title', $action === 'edit' ? __('labels.edit_location') : __('labels.add_location'))

@section('header_data')
    @php
        $page_title = $action === 'edit' ? __('labels.edit_location') : __('labels.add_location');
        $page_pretitle = $store->name;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.stores'), 'url' => route('admin.sellers.store.index')],
        ['title' => __('labels.locations'), 'url' => route('admin.sellers.store.locations.index', ['storeId' => $store->id])],
        ['title' => $action === 'edit' ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];
@endphp

@section('admin-content')
    @include('seller.store_locations._form', [
        'store'       => $store,
        'location'    => $location,
        'action'      => $action,
        'breadcrumbs' => $breadcrumbs,
        'panel'       => 'admin',
    ])
@endsection
