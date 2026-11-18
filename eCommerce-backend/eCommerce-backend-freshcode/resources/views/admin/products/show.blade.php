@extends('layouts.admin.app', ['page' => $menuAdmin['products']['active'] ?? '', 'sub_page' => $menuAdmin['products']['route']['products']['sub_active']])

@section('title', __('labels.product_details'))

@section('header_data')
    @php
        $page_title = __('labels.product_details');
        $page_pretitle = __('labels.admin').' '.__('labels.product_details');
    @endphp
@endsection

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
                @include('partials.product-detail-content', ['panel' => 'admin'])
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/product-detail.css') }}">
@endpush

@push('scripts')
    <script src="{{ hyperAsset('assets/js/product.js') }}" defer></script>
@endpush
