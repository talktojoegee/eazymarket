@extends('layouts.admin.app', ['page' => $menuAdmin['stores']['active'] ?? ''])

@section('title', __('labels.locations'))

@section('header_data')
    @php
        $page_title = __('labels.locations');
        $page_pretitle = $store->name;
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.stores'), 'url' => route('admin.sellers.store.index')],
        ['title' => $store->name, 'url' => route('admin.sellers.store.show', $store->id)],
        ['title' => __('labels.locations'), 'url' => null],
    ];
@endphp

@section('admin-content')
    @include('seller.store_locations._index', [
        'store'            => $store,
        'locations'        => $locations,
        'createPermission' => $createPermission,
        'editPermission'   => $editPermission,
        'deletePermission' => $deletePermission,
        'breadcrumbs'      => $breadcrumbs,
        'panel'            => 'admin',
    ])
@endsection
