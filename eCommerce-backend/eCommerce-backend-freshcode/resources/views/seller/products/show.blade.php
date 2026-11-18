@extends('layouts.seller.app', ['page' => $menuSeller['products']['active'] ?? '', 'sub_page' => $menuSeller['products']['route']['products']['sub_active']])

@section('title', __('labels.product_details'))

@section('header_data')
    @php
        $page_title = __('labels.product_details');
        $page_pretitle = __('labels.seller').' '.__('labels.product_details');
    @endphp
@endsection

@section('seller-content')
    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
                @include('partials.product-detail-content', ['panel' => 'seller'])
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
