@extends('layouts.main')

@section('panel-header-scripts')
    @if(file_exists(base_path('admin-header-scripts.html')))
        {!! file_get_contents(base_path('admin-header-scripts.html')) !!}
    @endif
@endsection

@section('panel-footer-scripts')
    @if(file_exists(base_path('admin-footer-scripts.html')))
        {!! file_get_contents(base_path('admin-footer-scripts.html')) !!}
    @endif
@endsection

@section('header_data')
    @php
        $page_title = $page_title ?? 'Admin Dashboard';
        $page_pretitle = $page_pretitle ?? 'Overview';
    @endphp
@endsection
@section('content')
    @include('layouts.partials._header', [
        'page_title' => $page_title ?? 'Admin Dashboard',
        'page_pretitle' => $page_pretitle ?? 'Overview',
    ])
    <div class="page-body" data-tour-target="panel-content">
        <div class="container-xl">
            @yield('admin-content')
        </div>
    </div>
@endsection

@if(!request()->routeIs('admin.support.index') && (auth()->user()?->hasRole(\App\Enums\DefaultSystemRolesEnum::SUPER_ADMIN()) || auth()->user()?->can('support.view')))
    @push('script')
        <div id="support-notification-listener"
             data-realtime-url="{{ route('admin.support.realtime') }}"
             data-support-url="{{ route('admin.support.index') }}"
             data-agent-id="{{ auth()->id() }}"></div>
        <script src="{{ asset('assets/vendor/pusher/pusher.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/laravel-echo/echo.iife.js') }}"></script>
        <script src="{{ hyperAsset('assets/js/support-notifications.js') }}"></script>
    @endpush
@endif
