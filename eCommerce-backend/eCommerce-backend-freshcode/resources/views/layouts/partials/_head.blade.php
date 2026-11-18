<!-- BEGIN GLOBAL MANDATORY STYLES -->
<link href="{{asset('assets/vendor/tom-select/css/tom-select.bootstrap5.min.css')}}" rel="stylesheet"/>

@php($rtlTheme = ($textDirection ?? 'ltr') === 'rtl' ? '.rtl' : '')
<link href="{{asset("assets/theme/css/tabler{$rtlTheme}.css?v=123")}}" rel="stylesheet"/>
<link href="{{asset("assets/theme/css/tabler-flags{$rtlTheme}.css")}}" rel="stylesheet"/>
<link href="{{asset("assets/theme/css/tabler-socials{$rtlTheme}.css")}}" rel="stylesheet"/>
<link href="{{asset("assets/theme/css/tabler-payments{$rtlTheme}.css")}}" rel="stylesheet"/>
<link href="{{asset("assets/theme/css/tabler-vendors{$rtlTheme}.css")}}" rel="stylesheet"/>
<link href="{{asset("assets/theme/css/tabler-marketing{$rtlTheme}.css")}}" rel="stylesheet"/>
<link href="{{asset("assets/theme/css/tabler-themes{$rtlTheme}.css")}}" rel="stylesheet"/>
<link href="{{hyperAsset('assets/theme/css/style.css')}}" rel="stylesheet"/>
<link href="{{asset('assets/vendor/filepond/css/filepond.css')}}" rel="stylesheet"/>

<link rel="stylesheet" href="{{asset('assets/theme/css/tabler-icons.min.css')}}"/>

<link
    href="{{asset('assets/vendor/filepond/css/filepond-plugin-image-preview.css')}}"
    rel="stylesheet"
/>

<link
    href="{{asset('assets/vendor/sweetalert/css/sweetalert2.min.css')}}"
    rel="stylesheet"
/>

<link
    href="{{asset('assets/vendor/datatable/css/dataTables.css')}}"
    rel="stylesheet"
/>
<link
    href="{{asset('assets/vendor/datatable/css/buttons.bootstrap5.css')}}"
    rel="stylesheet"
/>


<link href="{{hyperAsset('assets/admin/css/custom.css')}}" rel="stylesheet"/>
<link href="{{hyperAsset('assets/css/custom.css')}}" rel="stylesheet"/>
<link href="{{hyperAsset('assets/css/global-search.css')}}" rel="stylesheet"/>
<link href="{{ asset('assets/vendor/driverjs/driver.css') }}" rel="stylesheet"/>

@stack('styles')
<style>
    @import url("https://rsms.me/inter/inter.css");
</style>
