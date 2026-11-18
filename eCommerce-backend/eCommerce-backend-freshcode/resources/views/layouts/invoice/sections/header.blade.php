<header class="invoice-header" data-invoice-section="header" style="{{ $sectionStyle('header') }}">
    <div class="invoice-brand">
        @if(($invoiceConfig['showLogo'] ?? true) && $logo)<img class="invoice-logo" src="{{ $logo }}" alt="{{ $appName }}">@endif
        <strong class="invoice-brand-name">{{ $appName }}</strong>
        @if(!empty($systemSettings['companyAddress']))<small>{{ $systemSettings['companyAddress'] }}</small>@endif
        <small>{{ collect([$systemSettings['sellerSupportEmail'] ?? null, $systemSettings['sellerSupportNumber'] ?? null])->filter()->implode(' · ') }}</small>
    </div>
    <div class="invoice-heading">
        <h1>{{ $invoiceConfig['title'] ?? __('labels.tax_invoice') }}</h1>
        <small>{{ __('labels.invoice') }} # {{ $orderReference }}</small>
    </div>
</header>
