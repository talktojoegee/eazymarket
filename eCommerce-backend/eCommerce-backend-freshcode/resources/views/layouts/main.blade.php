<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">

<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            try {
                if (localStorage.getItem('hyper-sidebar-slim') === 'true') {
                    document.documentElement.classList.add('sidebar-slim');
                }
            } catch (e) {}
        })();
    </script>
    <title>@yield('title')
        | {{ !empty($systemSettings['appName']) ? $systemSettings['appName'] : config('app.name') }}</title>
    <link rel="icon" href="{{ $systemSettings['favicon'] ?? asset('logos/hyper-favicon.png') }}"
          sizes="image/x-icon">
    @include('layouts.partials._head')
    @include('components.google-font')
    @yield('panel-header-scripts')
</head>

<body class="layout-fluid">
<input type="hidden" name="base_url" id="base_url" value="{{url('/')}}">
<input type="hidden" name="user_id" id="user_id" value="{{$user->id ?? ""}}">
<input type="hidden" name="panel" id="panel" data-panel="{{ $panel ?? 'admin' }}">
<input type="hidden" id="selected-currency-symbol" value="{{ $systemSettings['currencySymbol'] ?? '$' }}">
<script src="{{asset('assets/theme/js/tabler-theme.min.js')}}"></script>
<div class="page overflow-hidden">
    @include('layouts.partials._sidebar')
    <div class="page-wrapper">

        @include('layouts.partials._alerts')
        @yield('content')

        @include('layouts.partials._footer')
    </div>
</div>

@if(auth()->check() && in_array(request()->segment(1), ['admin', 'seller'], true))
    @include('components.global-search-modal')
    @include('components.panel-relogin-modal')
    <x-tour-drawer/>
    @can('viewAny', \App\Models\Order::class)
        @include('components.panel-new-order-modal')
    @endcan
@endif

@include('layouts.partials._scripts')
@if(auth()->check() && in_array(request()->segment(1), ['admin', 'seller'], true))
    <script src="{{ hyperAsset('assets/js/panel-session.js') }}" defer></script>
@endif
@stack('script')
@yield('panel-footer-scripts')
</body>

</html>
