<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" class="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | {{ !empty($systemSettings['appName']) ? $systemSettings['appName'] : config('app.name') }}</title>
    <link rel="icon" href="{{ $systemSettings['favicon'] ?? asset('logos/hyper-favicon.png') }}" sizes="image/x-icon">

    <script>
        (function () {
            try {
                var t = localStorage.getItem('hui-theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.remove('light');
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <link rel="stylesheet" href="{{ asset('assets/theme/css/tabler-icons.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/vendor/filepond/css/filepond.css') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/vendor/filepond/css/filepond-plugin-image-preview.css') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/vendor/sweetalert/css/sweetalert2.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/vendor/tom-select/css/tom-select.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatable/css/dataTables.css') }}"/>
    <link rel="stylesheet" href="{{ hyperAsset('assets/theme/css/tailwind.css') }}"/>
    @include('components.google-font')
    @stack('styles')
</head>

<body class="bg-[#f7f7f8] dark:bg-ink-950 text-ink dark:text-neutral-200 antialiased" style="font-family:var(--tblr-font-sans-serif)">
<input type="hidden" id="base_url" value="{{ url('/') }}">
<input type="hidden" id="user_id" value="{{ $user->id ?? '' }}">
<input type="hidden" id="panel" data-panel="{{ $panel ?? request()->segment(1) }}">
<input type="hidden" id="selected-currency-symbol" value="{{ $systemSettings['currencySymbol'] ?? '$' }}">

<div class="flex min-h-screen">
    @include('layouts.tw.partials._sidebar')

    <div class="flex-1 min-w-0 flex flex-col">
        @include('layouts.tw.partials._topbar')
        @include('layouts.partials._alerts')

        <main class="flex-1 overflow-x-hidden">
            <div class="max-w-[1200px] mx-auto px-4 lg:px-6 py-6">
                @yield('admin-content')
                @yield('seller-content')
                @yield('content-body')
            </div>
        </main>
    </div>
</div>

<script src="{{ asset('assets/vendor/axios/axios.min.js') }}"></script>
<script src="{{ asset('assets/vendor/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond.min.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond-plugin-image-preview.min.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond-plugin-file-validate-type.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond-plugin-file-validate-size.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond.jquery.js') }}"></script>
<script src="{{ hyperAsset('assets/js/filepond.custom.js') }}"></script>
<script src="{{ asset('assets/vendor/tom-select/js/tom-select.base.min.js') }}"></script>
<script src="{{ hyperAsset('assets/js/tom-select-pickers.js') }}"></script>
<script src="{{ asset('assets/vendor/sweetalert/js/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatable/js/dataTables.js') }}"></script>
<script src="{{ asset('assets/vendor/datatable/js/dataTables.buttons.js') }}"></script>
<script src="{{ asset('assets/vendor/datatable/js/buttons.colVis.min.js') }}"></script>
<script src="{{ asset('assets/vendor/datatable/js/buttons.html5.min.js') }}"></script>
<script src="{{ hyperAsset('assets/js/datatable.custom.js') }}"></script>
<script src="{{ hyperAsset('assets/js/hyper-ui.js') }}"></script>
<script src="{{ hyperAsset('assets/js/sidebar.js') }}"></script>
<script src="{{ hyperAsset('assets/js/custom.js') }}"></script>
@stack('vendor-scripts')

<script>
    (function () {
        var root = document.documentElement;
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-hui-theme-toggle]')) {
                var dark = root.classList.toggle('dark');
                root.classList.toggle('light', !dark);
                try { localStorage.setItem('hui-theme', dark ? 'dark' : 'light'); } catch (err) {}
            }
            if (e.target.closest('[data-hui-sidebar-open]')) openSidebar();
            if (e.target.closest('[data-hui-sidebar-close]') || e.target.closest('[data-hui-sidebar-backdrop]')) closeSidebar();
        });
        function openSidebar() {
            document.getElementById('hui-sidebar').classList.remove('-translate-x-full');
            document.querySelector('[data-hui-sidebar-backdrop]').classList.remove('hidden');
        }
        function closeSidebar() {
            document.getElementById('hui-sidebar').classList.add('-translate-x-full');
            document.querySelector('[data-hui-sidebar-backdrop]').classList.add('hidden');
        }
        document.addEventListener('hui:tab:shown', function () {
            if (window.jQuery && jQuery.fn.dataTable) {
                jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            }
        });
    })();
</script>
@stack('scripts')
@stack('script')
</body>
</html>
