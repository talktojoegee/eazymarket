@php
    $__demo = $systemSettings['demoMode'] ?? false;
    $__fallback = asset('logos/hyper-logo.png');
    $__light = $__demo ? $__fallback : (!empty($systemSettings['logo']) ? $systemSettings['logo'] : $__fallback);
    $__dark = $__demo ? '' : ($systemSettings['darkLogo'] ?? '');
    $__alt = $systemSettings['appName'] ?? '';
    $__width = $width ?? '150px';
@endphp

@if(!empty($__dark))
    <style>
        .auth-logo-dark { display: none; }
        [data-bs-theme="dark"] .auth-logo-light { display: none; }
        [data-bs-theme="dark"] .auth-logo-dark { display: inline-block; }
    </style>
    <img class="auth-logo-light" src="{{ $__dark }}" alt="{{ $__alt }}" width="{{ $__width }}">
    <img class="auth-logo-dark" src="{{ $__light }}" alt="{{ $__alt }}" width="{{ $__width }}">
@else
    <img src="{{ $__light }}" alt="{{ $__alt }}" width="{{ $__width }}">
@endif
