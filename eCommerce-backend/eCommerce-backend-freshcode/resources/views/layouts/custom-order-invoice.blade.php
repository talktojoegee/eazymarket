@php
    use Illuminate\Support\Str;

    $invoiceConfig = $invoiceConfig ?? [];
    $sections = collect(data_get($invoiceConfig, 'sections', []))->keyBy('key');
    $sectionEnabled = fn (string $key): bool => (bool) data_get($sections->get($key), 'enabled', true);
    $sectionStyle = function (string $key) use ($sections): string {
        $section = $sections->get($key, []);
        return collect([
            '--section-title-color' => data_get($section, 'titleColor'),
            '--section-subtitle-color' => data_get($section, 'subtitleColor'),
            '--section-background-color' => data_get($section, 'backgroundColor'),
            '--items-header-title-color' => data_get($section, 'itemHeaderTitleColor'),
            '--items-header-background-color' => data_get($section, 'itemHeaderBackgroundColor'),
            '--items-data-title-color' => data_get($section, 'itemDataTitleColor'),
            '--items-data-subtitle-color' => data_get($section, 'itemDataSubtitleColor'),
            '--items-data-background-color' => data_get($section, 'itemDataBackgroundColor'),
        ])->filter(fn ($value) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value))
            ->map(fn ($value, $property) => $property.':'.$value)
            ->implode(';');
    };
    $sectionKeys = ['header', 'details', 'addresses', 'seller_details', 'items', 'totals', 'payment', 'terms', 'signature', 'footer'];
    $orderedSections = collect(data_get($invoiceConfig, 'sections', []))
        ->filter(fn ($section) => is_array($section) && in_array($section['key'] ?? '', $sectionKeys, true))
        ->values();
    $brandColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($invoiceConfig['brandColor'] ?? '')) ? $invoiceConfig['brandColor'] : '#206bc4';
    $pageBackgroundColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($invoiceConfig['pageBackgroundColor'] ?? '')) ? $invoiceConfig['pageBackgroundColor'] : '#ffffff';
    $showPageLines = (bool) ($invoiceConfig['showPageLines'] ?? false);
    $pageLineColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($invoiceConfig['pageLineColor'] ?? '')) ? $invoiceConfig['pageLineColor'] : '#f4c20d';
    $font = trim((string) ($invoiceConfig['font'] ?? 'Inter'));
    $font = preg_match('/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u', $font) ? $font : 'Inter';
    $systemFonts = ['Arial', 'Georgia', 'Helvetica', 'Times New Roman'];
    $layout = in_array($invoiceConfig['layout'] ?? 'classic', ['classic', 'modern', 'compact'], true) ? $invoiceConfig['layout'] : 'classic';
    $templateType = in_array($invoiceConfig['templateType'] ?? 'green', ['grid', 'green', 'blue'], true) ? $invoiceConfig['templateType'] : 'green';
    $margin = min(30, max(5, (int) ($invoiceConfig['margin'] ?? 15)));
    $logo = $systemSettings['darkLogo'] ?? $systemSettings['logo'] ?? null;
    $appName = $systemSettings['appName'] ?? config('app.name', 'HyperCommerce');
    $orderTotals = data_get($order, 'order_summary.converted_currency', []);
    $itemsSubtotal = (float) (data_get($orderTotals, 'items_total') ?? $order['subtotal'] ?? 0);
    $shippingTotal = (float) (data_get($orderTotals, 'delivery_charge') ?? $order['delivery_charge'] ?? 0);
    $promoDiscount = (float) (data_get($orderTotals, 'promo_discount') ?? $order['promo_discount'] ?? 0);
    $taxTotal = (float) (data_get($orderTotals, 'tax_total') ?? $order['tax_total'] ?? 0);
    $orderTotal = (float) (data_get($orderTotals, 'order_total') ?? $order['final_total'] ?? 0);
    $payableTotal = (float) (data_get($orderTotals, 'payable_amount') ?? $order['total_payable'] ?? 0);
    $billingName = $order['billing_name'] ?? $order['shipping_name'] ?? '—';
    $billingAddress = collect([$order['billing_address_1'] ?? null, $order['billing_address_2'] ?? null, $order['billing_city'] ?? null, $order['billing_state'] ?? null, $order['billing_zip'] ?? null, $order['billing_country'] ?? null])->filter()->implode(', ');
    $billingContact = collect([$order['email'] ?? null, $order['billing_phone'] ?? null])->filter()->implode(' · ');
    $sellerNames = $sellerOrder->map(fn ($entry) => $entry->items->first()?->orderItem?->store?->name)->filter()->unique()->values();
    $sellerName = $sellerNames->count() > 1 ? 'Multiple sellers' : ($sellerNames->first() ?? 'Seller');
    $orderReference = Str::upper(Str::substr((string) $order['uuid'], 0, 12));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoiceConfig['title'] ?? __('labels.tax_invoice') }} #{{ $order['order_number'] ?? $order['id'] }}</title>
    @if(!in_array($font, $systemFonts, true))
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ urlencode($font) }}&display=swap">
    @endif
    <style>
        :root { --invoice-brand:{{ $brandColor }}; --invoice-ink:#182433; --invoice-muted:#617286; --invoice-line:#e2e8f0; --invoice-soft:#f5f8fc; }
        * { box-sizing:border-box; }
        body { margin:0; padding:22px; background:#edf2f8; color:var(--invoice-ink); font:13px/1.45 '{{ $font }}',Arial,sans-serif; }
        .invoice-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; width:210mm; max-width:100%; margin:0 auto 14px; }
        .invoice-toolbar button { border:1px solid var(--invoice-brand); border-radius:8px; background:var(--invoice-brand); color:#fff; padding:9px 14px; font:inherit; cursor:pointer; }
        .invoice-toolbar a { color:var(--invoice-muted); text-decoration:none; }
        .invoice-sheet { position:relative; width:210mm; min-height:297mm; max-width:100%; margin:0 auto 22px; background:#fff; box-shadow:0 7px 22px #15223825; border-top:0!important; border-bottom:0!important; }
        .invoice-sheet.has-page-lines:before,.invoice-sheet.has-page-lines:after { content:""; position:absolute; z-index:2; left:4px; right:4px; height:9px; background:var(--invoice-page-line-color); }
        .invoice-sheet.has-page-lines:before { top:0; }
        .invoice-sheet.has-page-lines:after { bottom:0; }
        .invoice-page { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); align-content:start; column-gap:12px; min-height:297mm; padding:{{ $margin }}mm; }
        .invoice-page>[data-invoice-section]:not([data-invoice-section="details"]):not([data-invoice-section="addresses"]) { grid-column:1/-1; }
        .invoice-page>[data-invoice-section="details"],.invoice-page>[data-invoice-section="addresses"] { grid-column:span 1; }
        .invoice-page:not(.has-addresses)>[data-invoice-section="details"] { grid-column:1/-1; }
        .invoice-layout--modern .invoice-header { border-top:7px solid var(--invoice-brand); background:var(--invoice-soft); padding:16px 12px; }
        .invoice-layout--compact .invoice-page { padding:8mm; }
        .invoice-template--tax .invoice-sheet { border-top:7px solid var(--invoice-brand); }
        .invoice-template--receipt .invoice-sheet { width:160mm; min-height:220mm; }
        .invoice-template--receipt .invoice-page { min-height:220mm; padding:10mm; }
        .invoice-template--receipt .invoice-header { flex-direction:column; align-items:center; text-align:center; }
        .invoice-template--receipt .invoice-brand,.invoice-template--receipt .invoice-heading { justify-items:center; text-align:center; }
        .invoice-template--receipt .invoice-logo { object-position:center; }
        .invoice-template--receipt .invoice-summary { width:100%; }
        .invoice-template--grid .invoice-sheet { background:#fffed8; border:2px solid #a4b17f; }
        .invoice-template--grid .invoice-page { background:#fffed8; color:#33402b; }
        .invoice-template--grid .invoice-header { justify-content:space-between; flex-direction:row; align-items:flex-start; border:1px solid #8f9d74; background:#e7efbf; padding:10px; text-align:left; }
        .invoice-template--grid .invoice-brand { justify-items:start; }
        .invoice-template--grid .invoice-heading { align-self:flex-start; text-align:right; }
        .invoice-template--grid .invoice-parties { gap:0; border:1px solid #8f9d74; background:#f6f9dd; }
        .invoice-template--grid .invoice-detail-card { border:0; border-right:1px solid #8f9d74; border-radius:0; background:transparent; }
        .invoice-template--grid .invoice-detail-card:last-child { border-right:0; }
        .invoice-template--grid .invoice-details-section { border-color:#8f9d74; background:#f6f9dd; }
        .invoice-template--grid .invoice-seller { padding:8px; border:1px solid #8f9d74; background:#f6f9dd; }
        .invoice-template--grid .invoice-table th,.invoice-template--grid .invoice-table td { border:1px solid #8f9d74; }
        .invoice-template--grid .invoice-table th { background:#dce8a8; color:#26351f; }
        .invoice-template--grid .invoice-summary { width:100%; border:1px solid #8f9d74; background:#f8fbdc; }
        .invoice-template--grid .invoice-summary-row { border-color:#a4b17f; }
        .invoice-template--grid .invoice-summary-row--total { background:#dce8a8; }
        .invoice-template--grid .invoice-payment,.invoice-template--grid .invoice-terms { border-color:#8f9d74; background:#f8fbdc; }
        .invoice-template--green .invoice-sheet { border-top:8px solid #326b3f; border-bottom:8px solid #326b3f; }
        .invoice-template--green .invoice-header { border:0; padding-bottom:12px; }
        .invoice-template--green .invoice-heading h1 { color:#2d3930; font-weight:400; }
        .invoice-template--green .invoice-table th { background:#326b3f; }
        .invoice-template--green .invoice-summary-row--total { border-bottom-color:#326b3f; }
        .invoice-template--green .invoice-footer { margin:20px -{{ $margin }}mm -{{ $margin }}mm; padding:12px {{ $margin }}mm; background:#326b3f; color:#fff; }
        .invoice-template--blue .invoice-sheet { border-top:8px solid #183960; }
        .invoice-template--blue .invoice-header { padding:10px; background:#183960; border:0; color:#fff; }
        .invoice-template--blue .invoice-brand-name,.invoice-template--blue .invoice-brand small { color:#fff; }
        .invoice-template--blue .invoice-heading { color:#fff; }
        .invoice-template--blue .invoice-table th { background:#183960; }
        .invoice-template--blue .invoice-detail-card { border-radius:0; }
        .invoice-template--blue .invoice-summary-row--total { background:#183960; color:#fff; }
        .invoice-template--grid .invoice-sheet { background:#fff; border:1px solid var(--invoice-line); }
        .invoice-template--grid .invoice-page { background:#fff; color:var(--invoice-ink); }
        .invoice-template--grid .invoice-header { border-color:var(--invoice-line); background:var(--invoice-soft); }
        .invoice-template--grid .invoice-parties,.invoice-template--grid .invoice-seller,.invoice-template--grid .invoice-summary { border-color:var(--invoice-line); background:#fff; }
        .invoice-template--grid .invoice-detail-card { border-color:var(--invoice-line); }
        .invoice-template--grid .invoice-details-section { border-color:var(--invoice-line); background:#fff; }
        .invoice-template--grid .invoice-table th { background:var(--invoice-brand); color:#fff; }
        .invoice-template--grid .invoice-table th,.invoice-template--grid .invoice-table td { border-color:var(--invoice-line); }
        .invoice-template--grid .invoice-summary-row { border-color:var(--invoice-line); }
        .invoice-template--grid .invoice-summary-row--total { background:var(--invoice-soft); color:var(--invoice-brand); }
        .invoice-template--grid .invoice-payment,.invoice-template--grid .invoice-terms { border-color:var(--invoice-line); background:#fff; }
        .invoice-template--green .invoice-sheet { border-top-color:var(--invoice-brand); border-bottom-color:var(--invoice-brand); }
        .invoice-template--green .invoice-table th { background:var(--invoice-brand); }
        .invoice-template--green .invoice-summary-row--total { border-bottom-color:var(--invoice-brand); }
        .invoice-template--green .invoice-footer { background:var(--invoice-brand); }
        .invoice-template--blue .invoice-sheet { border-top-color:var(--invoice-brand); }
        .invoice-template--blue .invoice-header { background:var(--invoice-brand); }
        .invoice-template--blue .invoice-table th { background:var(--invoice-brand); }
        .invoice-template--blue .invoice-summary-row--total { background:var(--invoice-brand); }
        .invoice-header { display:flex; justify-content:space-between; align-items:flex-start; gap:24px; padding-bottom:18px; border-bottom:2px solid var(--invoice-brand); }
        .invoice-brand { display:grid; gap:6px; min-width:0; }
        .invoice-logo { width:150px; height:48px; object-fit:contain; object-position:left center; }
        .invoice-brand-name { color:var(--invoice-brand); font-weight:800; }
        .invoice-brand small,.invoice-heading small,.invoice-detail-card span,.invoice-seller small,.invoice-item-detail { color:var(--invoice-muted); font-size:10px; }
        .invoice-heading { text-align:right; color:var(--invoice-brand); }
        .invoice-heading h1 { margin:0 0 4px; font-size:22px; }
        .invoice-details-section { display:grid; align-content:start; gap:4px; min-height:80px; margin-top:16px; padding:10px; border:1px solid var(--invoice-line); border-radius:5px; font-size:10px; }
        .invoice-details-section .invoice-heading { text-align:left; padding:0; border:0; }
        .invoice-details-section .invoice-heading h1 { margin:0 0 4px; font-size:13px; }
        .invoice-parties { display:block; margin-top:16px; }
        .invoice-detail-card { display:grid; align-content:start; gap:4px; min-height:80px; padding:10px; border:1px solid var(--invoice-line); border-radius:5px; font-size:10px; }
        .invoice-metadata { display:grid; gap:4px; margin-top:0; font-size:10px; }
        .invoice-detail-card strong { margin-bottom:2px; }
        .invoice-detail-card span { display:block; overflow-wrap:anywhere; }
        .invoice-seller { display:grid; gap:4px; margin:20px 0 16px; color:var(--invoice-brand); }
        .invoice-page h2 { margin:15px 0 10px; font-size:14px; }
        .invoice-table-wrap { overflow-x:auto; }
        .invoice-table { width:100%; border-collapse:collapse; font-size:10px; }
        .invoice-table th { padding:8px 6px; background:var(--invoice-brand); color:#fff; text-align:left; white-space:nowrap; }
        .invoice-table td { padding:9px 6px; border-bottom:1px solid var(--invoice-line); vertical-align:top; }
        .invoice-table td:first-child { min-width:190px; }
        .invoice-table small { display:block; margin-top:3px; color:var(--invoice-muted); font-size:9px; }
        .invoice-table th:not(:first-child),.invoice-table td:not(:first-child) { text-align:right; }
        [data-invoice-section][style*="--section-background-color"] { background-color:var(--section-background-color)!important; }
        [data-invoice-section="addresses"][style*="--section-background-color"] .invoice-detail-card { background-color:var(--section-background-color)!important; }
        [data-invoice-section="items"][style*="--section-background-color"] .invoice-table { background-color:var(--section-background-color)!important; }
        [data-invoice-section][style*="--section-title-color"]>h1,[data-invoice-section][style*="--section-title-color"]>h2,[data-invoice-section][style*="--section-title-color"]>strong,[data-invoice-section][style*="--section-title-color"] .invoice-heading h1,[data-invoice-section][style*="--section-title-color"] .invoice-brand-name,[data-invoice-section][style*="--section-title-color"] .invoice-detail-card strong { color:var(--section-title-color)!important; }
        [data-invoice-section="payment"][style*="--section-title-color"],[data-invoice-section="payment"][style*="--section-title-color"] *,[data-invoice-section="terms"][style*="--section-title-color"],[data-invoice-section="terms"][style*="--section-title-color"] *,[data-invoice-section="footer"][style*="--section-title-color"],[data-invoice-section="footer"][style*="--section-title-color"] * { color:var(--section-title-color)!important; }
        [data-invoice-section="totals"][style*="--section-background-color"] .invoice-summary-row { background-color:var(--section-background-color)!important; }
        [data-invoice-section="totals"][style*="--section-title-color"] .invoice-summary-row--total,[data-invoice-section="totals"][style*="--section-title-color"] .invoice-summary-row--total * { color:var(--section-title-color)!important; }
        [data-invoice-section="totals"][style*="--section-subtitle-color"] .invoice-summary-row:not(.invoice-summary-row--total),[data-invoice-section="totals"][style*="--section-subtitle-color"] .invoice-summary-row:not(.invoice-summary-row--total) * { color:var(--section-subtitle-color)!important; }
        [data-invoice-section][style*="--section-subtitle-color"] small,[data-invoice-section][style*="--section-subtitle-color"] .invoice-metadata,[data-invoice-section][style*="--section-subtitle-color"] .invoice-heading small,[data-invoice-section][style*="--section-subtitle-color"] .invoice-detail-card span { color:var(--section-subtitle-color)!important; }
        [data-invoice-section="items"][style*="--items-header-title-color"] .invoice-table thead th { color:var(--items-header-title-color)!important; }
        [data-invoice-section="items"][style*="--items-header-background-color"] .invoice-table thead th { background-color:var(--items-header-background-color)!important; }
        [data-invoice-section="items"][style*="--items-data-title-color"] .invoice-table tbody td strong { color:var(--items-data-title-color)!important; }
        [data-invoice-section="items"][style*="--items-data-subtitle-color"] .invoice-table tbody td { color:var(--items-data-subtitle-color)!important; }
        [data-invoice-section="items"][style*="--items-data-subtitle-color"] .invoice-table tbody td small { color:var(--items-data-subtitle-color)!important; }
        [data-invoice-section="items"][style*="--items-data-background-color"] .invoice-table tbody tr { background-color:var(--items-data-background-color)!important; }
        [data-invoice-section="items"][style*="--items-header-title-color"] thead th { color:var(--items-header-title-color)!important; }
        [data-invoice-section="items"][style*="--items-header-background-color"] thead th { background-color:var(--items-header-background-color)!important; }
        [data-invoice-section="items"][style*="--items-data-title-color"] tbody td strong { color:var(--items-data-title-color)!important; }
        [data-invoice-section="items"][style*="--items-data-subtitle-color"] tbody td small { color:var(--items-data-subtitle-color)!important; }
        [data-invoice-section="items"][style*="--items-data-background-color"] tbody tr { background-color:var(--items-data-background-color)!important; }
        .invoice-summary { width:54%; min-width:270px; margin:16px 0 12px auto; font-size:10px; }
        .invoice-summary-row { display:flex; justify-content:space-between; gap:15px; padding:6px 8px; border-bottom:1px solid var(--invoice-line); }
        .invoice-summary-row--total { color:var(--invoice-brand); font-weight:800; }
        .invoice-payment,.invoice-terms { margin-top:12px; padding:10px; border:1px solid var(--invoice-line); border-radius:5px; font-size:10px; }
        .invoice-signature { display:flex; flex-direction:column; align-items:center; align-self:flex-end; justify-self:end; width:max-content; margin:36px 0 20px; text-align:center; font-size:10px; }
        .invoice-signature img { display:block; max-width:150px; max-height:48px; margin:0 0 5px; object-fit:contain; }
        .invoice-footer { display:flex; justify-content:space-between; gap:15px; margin-top:40px; padding-top:12px; border-top:1px solid var(--invoice-line); color:var(--invoice-muted); font-size:9px; }
        @page { size:A4; margin:0; }
        @media print {
            html,body,.invoice-sheet,.invoice-page,.invoice-page *,.invoice-sheet:before,.invoice-sheet:after { -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
            body { padding:0; background:#fff; }
            .invoice-toolbar { display:none; }
            .invoice-sheet { width:210mm; min-height:0; margin:0; box-shadow:none; break-inside:auto; page-break-inside:auto; }
            .invoice-page { display:grid; min-height:0; }
            .invoice-sheet.has-page-lines:before,.invoice-sheet.has-page-lines:after { position:fixed; left:4px; right:4px; }
            .invoice-sheet.has-page-lines:before { top:0; }
            .invoice-sheet.has-page-lines:after { bottom:0; }
            .invoice-header,.invoice-parties,.invoice-seller,.invoice-summary,.invoice-payment,.invoice-terms,.invoice-signature,.invoice-footer { break-inside:avoid; page-break-inside:avoid; }
            .invoice-items,.invoice-table-wrap,.invoice-table { break-inside:auto; page-break-inside:auto; }
            .invoice-table thead { display:table-header-group; }
            .invoice-table tfoot { display:table-footer-group; }
            .invoice-table tr { break-inside:avoid; page-break-inside:avoid; }
        }
        @media(max-width:700px) { body { padding:8px; } .invoice-page { grid-template-columns:1fr; padding:22px 16px 18px; } .invoice-page>[data-invoice-section="details"],.invoice-page>[data-invoice-section="addresses"] { grid-column:1/-1; } .invoice-header { flex-direction:column; } .invoice-heading { text-align:left; } .invoice-summary { width:100%; min-width:0; } .invoice-footer { flex-direction:column; } }
    </style>
</head>
<body class="invoice-layout--{{ $layout }} invoice-template--{{ $templateType }}">
    <div class="invoice-toolbar">
        <a href="javascript:history.back()">{{ __('labels.back') }}</a>
        <button type="button" onclick="window.print()">{{ __('labels.print') }} / Save PDF</button>
    </div>
    <article class="invoice-sheet{{ $showPageLines ? ' has-page-lines' : '' }}" style="--invoice-page-line-color:{{ $pageLineColor }}">
        <main class="invoice-page{{ $sectionEnabled('addresses') ? ' has-addresses' : '' }}" style="background-color:{{ $pageBackgroundColor }}">
            @foreach($orderedSections as $invoiceSection)
                @if($sectionEnabled($invoiceSection['key']))
                    @includeIf('layouts.invoice.sections.'.$invoiceSection['key'])
                @endif
            @endforeach
        </main>
    </article>
</body>
</html>
