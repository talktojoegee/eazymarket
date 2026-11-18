@php($googleFontFamily = trim((string) (request()->routeIs('seller.*') ? ($sellerSettings['sellerPanelFontFamily'] ?? '') : (request()->routeIs('admin.*') ? ($webSettings['adminPanelFontFamily'] ?? '') : ($webSettings['googleFontFamily'] ?? '')))) ?: 'Inter')
@if($googleFontFamily !== '')
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family={{ urlencode($googleFontFamily) }}&display=swap" rel="stylesheet" />
    <style>
        :root {
            --tblr-font-sans-serif: '{{ $googleFontFamily }}', Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
    </style>
@endif
