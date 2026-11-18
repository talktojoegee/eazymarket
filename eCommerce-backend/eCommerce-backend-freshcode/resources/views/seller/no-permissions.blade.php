@extends('layouts.seller.app', ['page' => 'no_permissions'])

@section('title', __('labels.no_permissions_assigned'))

@section('header_data')
    @php
        $page_title = __('labels.no_permissions_assigned');
        $page_pretitle = __('labels.access');
    @endphp
@endsection

@section('seller-content')
    <div class="card">
        <div class="card-body text-center py-5">
            <h2>{{ __('labels.no_permissions_assigned') }}</h2>
            <p class="text-secondary mb-0">{{ __('labels.no_permissions_assigned_message') }}</p>
        </div>
    </div>
@endsection
