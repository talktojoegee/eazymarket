@php
    use Illuminate\Support\Str;

    $originalTotals = data_get($order, 'order_summary.converted_currency', []);
    $itemsSubtotal = (float) (data_get($originalTotals, 'items_total') ?? $order['subtotal'] ?? 0);
    $shippingTotal = (float) (data_get($originalTotals, 'delivery_charge') ?? $order['delivery_charge'] ?? 0);
    $platformFee = (float) (data_get($originalTotals, 'platform_fee') ?? $order['platform_fee'] ?? 0);
    $codFee = (float) (data_get($originalTotals, 'cod_fee') ?? $order['cod_fee'] ?? 0);
    $promoDiscount = (float) (data_get($originalTotals, 'promo_discount') ?? $order['promo_discount'] ?? 0);
    $walletUsed = (float) (data_get($originalTotals, 'wallet_amount_used') ?? $order['wallet_balance'] ?? 0);
    $taxTotal = (float) (data_get($originalTotals, 'tax_total') ?? $order['tax_total'] ?? 0);
    $orderTotal = (float) (data_get($originalTotals, 'order_total') ?? $order['final_total'] ?? 0);
    $payableTotal = (float) (data_get($originalTotals, 'payable_amount') ?? $order['total_payable'] ?? 0);
    $invoiceConfig = $invoiceConfig ?? [];
    $invoiceSections = collect(data_get($invoiceConfig, 'sections', []))->keyBy('key');
    $invoiceSectionEnabled = fn (string $key): bool => (bool) data_get($invoiceSections->get($key), 'enabled', true);
    $invoiceSectionOrder = fn (string $key): int => 10 * ((int) $invoiceSections->keys()->search($key) + 1);
    $billingName = $order['billing_name'] ?? $order['shipping_name'] ?? '—';
    $orderReference = Str::upper(Str::substr((string) $order['uuid'], 0, 12));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('labels.invoice') }} - {{ $systemSettings['appName'] ?? 'HyperCommerce' }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ $systemSettings['favicon'] ?? asset('favicon.png') }}">
    <style>
        :root { --invoice-bg:#edf1f5; --invoice-paper:#fff; --invoice-ink:#18212b; --invoice-muted:#667085; --invoice-line:#dfe5ec; --invoice-soft:#f5f7fa; --invoice-brand:#1769e0; --invoice-success:#067647; --invoice-success-bg:#ecfdf3; }
        * { box-sizing:border-box; }
        body { margin:0; padding:22px; background:var(--invoice-bg); color:var(--invoice-ink); font:13px/1.5 {{ $invoiceConfig['font'] ?? 'Arial' }},sans-serif; --invoice-brand:{{ $invoiceConfig['brandColor'] ?? '#f4c20d' }}; }
        button { font:inherit; }
        .invoice-toolbar { width:210mm; max-width:100%; margin:0 auto 14px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .invoice-toolbar__group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .invoice-button { display:inline-flex; align-items:center; gap:7px; border:1px solid var(--invoice-line); background:var(--invoice-paper); color:var(--invoice-ink); border-radius:8px; padding:8px 13px; text-decoration:none; cursor:pointer; }
        .invoice-button--primary { border-color:var(--invoice-brand); background:var(--invoice-brand); color:#fff; }
        .invoice-sheet { width:210mm; min-height:297mm; max-width:100%; margin:0 auto 22px; background:var(--invoice-paper); box-shadow:0 18px 45px rgba(17,24,39,.15); page-break-after:always; }
        .invoice-sheet:last-child { page-break-after:auto; }
        .invoice-accent { height:6px; background:var(--invoice-brand); }
        .invoice-page { display:flex; flex-direction:column; padding:{{ (int) ($invoiceConfig['margin'] ?? 15) }}mm; }
        .invoice-layout--modern .invoice-header { border:0; border-top:7px solid var(--invoice-brand); padding:16px 12px; background:var(--invoice-soft); }
        .invoice-layout--compact .invoice-page { padding:8mm; }
        .invoice-header { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:26px; padding-bottom:18px; border-bottom:2px solid var(--invoice-ink); }
        .invoice-issuer { display:block; gap:12px; align-items:flex-start; }
        .invoice-logo { width:82px; height:42px; flex:0 0 42px; display:grid; place-items:center; overflow:hidden; border-radius:9px; background:var(--invoice-brand); color:#fff; font-size:17px; font-weight:800; }
        .invoice-logo img { width:100%; height:100%; object-fit:contain; background:#fff; }
        .invoice-issuer h2,.invoice-title h1 { margin:0; line-height:1.2; }
        .invoice-issuer h2 { margin-bottom:5px; font-size:18px; }
        .invoice-title h1 { font-size:24px; text-transform:uppercase; letter-spacing:.02em; }
        .invoice-muted { color:var(--invoice-muted); }
        .invoice-title-row { display:flex; align-items:center; justify-content:flex-end; gap:10px; margin-bottom:9px; }
        .invoice-badge { padding:3px 9px; border-radius:999px; background:var(--invoice-success-bg); color:var(--invoice-success); font-size:10px; font-weight:800; text-transform:uppercase; }
        .invoice-meta { display:grid; grid-template-columns:auto auto; gap:3px 12px; margin:0; }
        .invoice-meta dt { color:var(--invoice-muted); text-align:right; }
        .invoice-meta dd { margin:0; max-width:210px; overflow-wrap:anywhere; font-weight:700; }
        .invoice-parties { display:grid; grid-template-columns:1fr 1fr; gap:32px; padding:18px 0; border-bottom:1px solid var(--invoice-line); }
        .invoice-label { margin-bottom:7px; color:var(--invoice-muted); font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .invoice-party-name { margin-bottom:4px; font-weight:700; }
        .invoice-strip { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); margin:15px 0 18px; overflow:hidden; border:1px solid var(--invoice-line); border-radius:8px; }
        .invoice-stat { padding:9px 10px; background:var(--invoice-soft); border-right:1px solid var(--invoice-line); }
        .invoice-stat:last-child { border-right:0; }
        .invoice-stat span { display:block; margin-bottom:2px; color:var(--invoice-muted); font-size:10px; text-transform:uppercase; }
        .invoice-stat strong { font-size:12px; }
        .invoice-table-wrap { overflow-x:auto; }
        .invoice-table { width:100%; border-collapse:collapse; }
        .invoice-table th { padding:8px 7px; background:var(--invoice-soft); color:var(--invoice-muted); border-bottom:1px solid var(--invoice-line); font-size:10px; letter-spacing:.04em; text-align:right; text-transform:uppercase; white-space:nowrap; }
        .invoice-table th:first-child,.invoice-table td:first-child { text-align:left; }
        .invoice-table td { padding:11px 7px; border-bottom:1px solid var(--invoice-line); text-align:right; vertical-align:top; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .invoice-table td:first-child { min-width:210px; white-space:normal; }
        .invoice-item-title { margin-bottom:3px; font-weight:700; }
        .invoice-item-detail { color:var(--invoice-muted); font-size:11px; }
        .invoice-bottom { display:grid; grid-template-columns:minmax(0,1fr) minmax(270px,.82fr); gap:34px; padding-top:18px; }
        .invoice-note { padding:12px; border:1px solid var(--invoice-line); border-radius:8px; background:var(--invoice-soft); color:var(--invoice-muted); }
        .invoice-summary { overflow:hidden; border:1px solid var(--invoice-line); border-radius:8px; }
        .invoice-summary-row,.invoice-grand-total { display:flex; justify-content:space-between; gap:15px; padding:7px 11px; font-variant-numeric:tabular-nums; }
        .invoice-summary-row { color:var(--invoice-muted); }
        .invoice-summary-row strong { color:var(--invoice-ink); }
        .invoice-grand-total { font-weight:800; }
        .invoice-signoff { display:grid; grid-template-columns:minmax(0,1fr) 220px; gap:30px; margin-top:22px; padding-top:18px; border-top:1px solid var(--invoice-line); }
        .invoice-signature { align-self:end; text-align:center; }
        .invoice-signature img { display:block; max-width:180px; max-height:58px; margin:0 auto 4px; object-fit:contain; }
        .invoice-signature-space { height:48px; }
        .invoice-signature-line { padding-top:6px; border-top:1px solid var(--invoice-ink); font-weight:700; }
        .invoice-footer { display:flex; justify-content:space-between; gap:20px; margin-top:20px; padding-top:12px; border-top:1px solid var(--invoice-line); color:var(--invoice-muted); font-size:10px; }
        @page { size:A4; margin:0; }
        @media print {
            body { padding:0; background:#fff; }
            .invoice-toolbar { display:none!important; }
            .invoice-sheet { width:210mm; min-height:0; margin:0; box-shadow:none; break-after:auto; page-break-after:auto; }
            .invoice-sheet + .invoice-sheet { break-before:page; page-break-before:always; }
            .invoice-page { padding:10mm 12mm 8mm; }
            .invoice-header,.invoice-parties,.invoice-strip,.invoice-table tr,.invoice-bottom,.invoice-signoff,.invoice-footer { break-inside:avoid; page-break-inside:avoid; }
        }
        @media (max-width:700px) { body { padding:8px; } .invoice-page { padding:22px 16px 18px; } .invoice-header,.invoice-parties,.invoice-bottom,.invoice-signoff { grid-template-columns:1fr; } .invoice-title-row { justify-content:flex-start; } .invoice-meta { justify-content:start; } .invoice-meta dt { text-align:left; } .invoice-strip { grid-template-columns:1fr 1fr; } .invoice-stat:nth-child(2) { border-right:0; } .invoice-stat:nth-child(-n+2) { border-bottom:1px solid var(--invoice-line); } .invoice-footer { flex-direction:column; } }
    </style>
</head>
<body class="invoice-layout--{{ $invoiceConfig['layout'] ?? 'classic' }}">
<div class="invoice-toolbar">
    <div class="invoice-toolbar__group">
        <button type="button" class="invoice-button" onclick="history.back()">← {{ __('labels.back') }}</button>
        <span class="invoice-muted">1 admin invoice + {{ $sellerOrder->count() }} seller {{ Str::plural('invoice', $sellerOrder->count()) }}</span>
    </div>
    <button type="button" class="invoice-button invoice-button--primary" onclick="window.print()">{{ __('labels.print') }} / Save PDF</button>
</div>

<article class="invoice-sheet">

    <div class="invoice-page">
        <header class="invoice-header" style="order:{{ $invoiceSectionOrder('header') }}">
            <div class="invoice-issuer">
                @if($invoiceConfig['showLogo'] ?? true)<div class="invoice-logo">
                    @if(!empty($systemSettings['darkLogo']) || !empty($systemSettings['logo']))
                        <img src="{{ !empty($systemSettings['darkLogo']) ? $systemSettings['darkLogo'] : $systemSettings['logo'] }}" alt="{{ $systemSettings['appName'] ?? '' }}">
                    @else
                        {{ Str::upper(Str::substr($systemSettings['appName'] ?? 'H', 0, 2)) }}
                    @endif
                </div>@endif
                <div>
                    <h2>{{ $systemSettings['appName'] ?? 'HyperCommerce' }}</h2>
                    @if(!empty($systemSettings['companyAddress']))<div class="invoice-muted">{!! nl2br(e($systemSettings['companyAddress'])) !!}</div>@endif
                    <div class="invoice-muted">{{ collect([$systemSettings['sellerSupportEmail'] ?? null, $systemSettings['sellerSupportNumber'] ?? null])->filter()->implode(' · ') }}</div>
                </div>
            </div>
            <div class="invoice-title" style="order:{{ $invoiceSectionOrder('details') }}">
                <div class="invoice-title-row"><h1>{{ $invoiceConfig['title'] ?? 'Tax Invoice' }}</h1></div>
                <dl class="invoice-meta">
                    <dt>Document ref.</dt><dd>{{ $orderReference }}</dd>
                    <dt>{{ __('labels.order_number') }}</dt><dd>#{{ $order['order_number'] ?? $order['id'] }}</dd>
                    <dt>{{ __('labels.order_date') }}</dt><dd>{{ $order['created_at']->format('d M Y') }}</dd>
                    @if($invoiceSectionEnabled('payment'))<dt>{{ __('labels.menu_payment') }}</dt><dd>{{ Str::headline((string) ($order['payment_method'] ?? '—')) }}@if($invoiceConfig['showPaymentStatus'] ?? true) · {{ Str::headline((string) ($order['payment_status'] ?? '—')) }}@endif</dd>@endif
                </dl>
            </div>
        </header>

        @if($invoiceSectionEnabled('addresses'))
        <section class="invoice-parties" style="order:{{ $invoiceSectionOrder('addresses') }}">
            <div>
                <div class="invoice-label">{{ __('labels.billed_to') }}</div>
                <div class="invoice-party-name">{{ $billingName }}</div>
                <div class="invoice-muted">{{ $order['billing_address_1'] ?? $order['shipping_address_1'] }}</div>
                @if(!empty($order['billing_address_2']))<div class="invoice-muted">{{ $order['billing_address_2'] }}</div>@endif
                <div class="invoice-muted">{{ collect([$order['billing_city'] ?? null, $order['billing_state'] ?? null, $order['billing_zip'] ?? null])->filter()->implode(', ') }}</div>
                <div class="invoice-muted">{{ collect([$order['billing_country'] ?? null, $order['billing_phone'] ?? null, $order['email'] ?? null])->filter()->implode(' · ') }}</div>
            </div>
            <div>
                <div class="invoice-label">{{ __('labels.ship_to') }}</div>
                <div class="invoice-party-name">{{ $order['shipping_name'] ?? '—' }}</div>
                <div class="invoice-muted">{{ $order['shipping_address_1'] ?? '' }}</div>
                @if(!empty($order['shipping_address_2']))<div class="invoice-muted">{{ $order['shipping_address_2'] }}</div>@endif
                <div class="invoice-muted">{{ collect([$order['shipping_city'] ?? null, $order['shipping_state'] ?? null, $order['shipping_zip'] ?? null])->filter()->implode(', ') }}</div>
                <div class="invoice-muted">{{ collect([$order['shipping_country'] ?? null, $order['shipping_phone'] ?? null])->filter()->implode(' · ') }}</div>
            </div>
        </section>
        @endif

        @if($invoiceSectionEnabled('seller_details'))
        <section class="invoice-strip" style="order:{{ $invoiceSectionOrder('seller_details') }}">
            <div class="invoice-stat"><span>Sellers</span><strong>{{ $sellerOrder->count() }}</strong></div>
            <div class="invoice-stat"><span>Currency</span><strong>{{ $order['currency_code'] ?? data_get($currency, 'code', '—') }}</strong></div>
            <div class="invoice-stat"><span>{{ __('labels.place_of_supply') }}</span><strong>{{ $order['shipping_state'] ?? '—' }}</strong></div>
            <div class="invoice-stat"><span>{{ __('labels.order_number') }}</span><strong>#{{ $order['order_number'] ?? $order['id'] }}</strong></div>
        </section>
        @endif

        @if($invoiceSectionEnabled('items'))
        <h2 style="order:{{ $invoiceSectionOrder('items') }}">Order Summary</h2>
        <div class="invoice-table-wrap" style="order:{{ $invoiceSectionOrder('items') }}">
            <table class="invoice-table">
                <thead><tr><th>{{ __('labels.item') }} details</th><th>Seller</th><th>{{ __('labels.qty') }}</th>@if($invoiceConfig['showItemStatus'] ?? true)<th>{{ __('labels.status') }}</th>@endif<th>{{ __('labels.unit_price') }}</th><th>{{ __('labels.tax') }}</th><th>{{ __('labels.total') }}</th></tr></thead>
                <tbody>
                @foreach($sellerOrder as $vendor)
                    @php
                        $vendorStore = $vendor['items'][0]['orderItem']['store'] ?? null;
                        $vendorName = $vendorStore['name'] ?? ($vendor['seller']['stores'][0]['name'] ?? 'Seller');
                    @endphp
                    @foreach($vendor['items'] as $item)
                        @php
                            $addons = $item->orderItem?->addons ?? collect();
                            $quantity = max(1, (int) $item['quantity']);
                            $unitPrice = (float) $item['price'];
                            $taxPercent = (float) ($item['orderItem']['tax_percent'] ?? 0);
                            $lineTax = round($quantity * $unitPrice * $taxPercent / 100, 2);
                            $addonTotal = round($quantity * (float) $addons->sum('price'), 2);
                            $lineTotal = round(($quantity * $unitPrice) + $addonTotal + $lineTax, 2);
                        @endphp
                        <tr>
                            <td>
                                <div class="invoice-item-title">{{ $item['orderItem']['title'] ?? $item['product']['title'] ?? 'Item' }}</div>
                                <div class="invoice-item-detail">{{ collect([$item['orderItem']['variant_title'] ?? $item['variant']['title'] ?? null, !empty($item['orderItem']['sku']) ? 'SKU: '.$item['orderItem']['sku'] : null, !empty($item['product']['hsn_code']) ? 'HSN: '.$item['product']['hsn_code'] : null])->filter()->implode(' · ') }}</div>
                                @if($invoiceConfig['showAddons'] ?? true)@foreach($addons as $addon)<div class="invoice-item-detail">+ {{ collect([$addon->addonGroup?->title, $addon->addonItem?->title])->filter()->implode(': ') }} · {{ formatOrderMoney((float) $addon->price, $currency) }}</div>@endforeach @endif
                            </td>
                            <td>{{ $vendorName }}</td>
                            <td>{{ $quantity }}</td>
                            @if($invoiceConfig['showItemStatus'] ?? true)<td>{{ Str::headline((string) ($item['status'] ?? '—')) }}</td>@endif
                            <td>{{ formatOrderMoney($unitPrice, $currency) }}</td>
                            <td><span class="invoice-item-detail">{{ number_format($taxPercent, 2) }}%</span><br>{{ formatOrderMoney($lineTax, $currency) }}</td>
                            <td><strong>{{ formatOrderMoney($lineTotal, $currency) }}</strong></td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($invoiceSectionEnabled('totals'))
        <section class="invoice-bottom" style="order:{{ $invoiceSectionOrder('totals') }}">
            <div class="invoice-note">
                <strong>Consolidated admin invoice</strong><br>
                This page summarises the original order across all sellers. Seller-specific tax invoices follow on separate pages.
            </div>
            <div class="invoice-summary">
                <div class="invoice-summary-row"><span>{{ __('labels.items_subtotal') }}</span><strong>{{ formatOrderMoney($itemsSubtotal, $currency) }}</strong></div>
                @if($promoDiscount > 0)<div class="invoice-summary-row"><span>{{ __('labels.promo_discount') }}</span><strong>- {{ formatOrderMoney($promoDiscount, $currency) }}</strong></div>@endif
                @if($shippingTotal > 0)<div class="invoice-summary-row"><span>{{ __('labels.shipping') }}</span><strong>{{ formatOrderMoney($shippingTotal, $currency) }}</strong></div>@endif
                @if($platformFee > 0)<div class="invoice-summary-row"><span>{{ __('labels.platform_fee') }}</span><strong>{{ formatOrderMoney($platformFee, $currency) }}</strong></div>@endif
                @if($codFee > 0)<div class="invoice-summary-row"><span>{{ __('labels.cod_fee') }}</span><strong>{{ formatOrderMoney($codFee, $currency) }}</strong></div>@endif
                <div class="invoice-summary-row"><span>{{ __('labels.tax') }} ({{ __('labels.included') }})</span><strong>{{ formatOrderMoney($taxTotal, $currency) }}</strong></div>
                @if($walletUsed > 0)<div class="invoice-summary-row"><span>{{ __('labels.wallet_used') }}</span><strong>- {{ formatOrderMoney($walletUsed, $currency) }}</strong></div>@endif
                <div class="invoice-grand-total"><span>{{ __('labels.total') }}</span><span>{{ formatOrderMoney($orderTotal, $currency) }}</span></div>
                @if($payableTotal !== $orderTotal)<div class="invoice-summary-row"><span>{{ $invoiceConfig['totalLabel'] ?? __('labels.total_payable') }}</span><strong>{{ formatOrderMoney($payableTotal, $currency) }}</strong></div>@endif
            </div>
        </section>
        @endif

        @if($invoiceSectionEnabled('signature'))
        <section class="invoice-signoff" style="order:{{ $invoiceSectionOrder('signature') }}">
            <div><div class="invoice-label">Declaration</div><div class="invoice-muted">This document reflects the original order values stored when the order was placed. Later cancellations, returns, and refunds are accounted for separately.</div></div>
            <div class="invoice-signature">
                @if(!empty($systemSettings['adminSignature']))<img src="{{ $systemSettings['adminSignature'] }}" alt="">@else<div class="invoice-signature-space"></div>@endif
                <div class="invoice-signature-line">{{ __('labels.authorized_signatory') }}</div>
                <div class="invoice-muted">For {{ $systemSettings['appName'] ?? 'HyperCommerce' }}</div>
            </div>
        </section>
        @endif

        @if($invoiceSectionEnabled('terms'))<div class="invoice-note" style="order:{{ $invoiceSectionOrder('terms') }}">{{ $invoiceConfig['terms'] ?? 'Please retain this invoice for your records.' }}</div>@endif
        @if($invoiceSectionEnabled('footer'))<footer class="invoice-footer" style="order:{{ $invoiceSectionOrder('footer') }}"><span>{{ $invoiceConfig['footer'] ?? collect([$systemSettings['sellerSupportEmail'] ?? null, $systemSettings['sellerSupportNumber'] ?? null])->filter()->implode(' · ') }}</span></footer>@endif
    </div>
</article>

@foreach($sellerOrder as $vendor)
    @php
        $store = $vendor['items'][0]['orderItem']['store'] ?? null;
        $sellerName = $store['name'] ?? ($vendor['seller']['stores'][0]['name'] ?? 'Seller');
        $sellerId = $vendor['seller']['id'] ?? $loop->iteration;
        $sellerSubtotal = (float) ($vendor['gross_subtotal'] ?: $vendor['total_price']);
        $sellerTax = (float) ($vendor['tax_amount'] ?? 0);
        $sellerDiscount = (float) ($vendor['promo_share'] ?? 0);
        $sellerShipping = (float) ($vendor['shipping_charge_applied'] ?? 0);
        $sellerTotal = max(0, round($sellerSubtotal + $sellerShipping - $sellerDiscount, 2));
    @endphp
    <article class="invoice-sheet">

        <div class="invoice-page">
            <header class="invoice-header" style="order:{{ $invoiceSectionOrder('header') }}">
                <div class="invoice-issuer">
                    <div>
                        <h2>{{ $sellerName }}</h2>
                        @if(!empty($store['address']))<div class="invoice-muted">{{ $store['address'] }}</div>@endif
                        <div class="invoice-muted">{{ collect([$store['city'] ?? null, $store['state'] ?? null, $store['zipcode'] ?? null])->filter()->implode(', ') }}</div>
                        @if(!empty($store['tax_number']))<div class="invoice-muted">{{ $store['tax_name'] ?? 'GSTIN' }}: {{ $store['tax_number'] }}</div>@endif
                    </div>
                </div>
                <div class="invoice-title" style="order:{{ $invoiceSectionOrder('details') }}">
                    <div class="invoice-title-row"><h1>{{ $invoiceConfig['title'] ?? __('labels.tax_invoice') }}</h1></div>
                    <dl class="invoice-meta">
                        <dt>{{ __('labels.order_number') }}</dt><dd>#{{ $order['order_number'] ?? $order['id'] }}</dd>
                        <dt>{{ __('labels.order_date') }}</dt><dd>{{ $order['created_at']->format('d M Y') }}</dd>
                        @if($invoiceSectionEnabled('payment'))<dt>{{ __('labels.menu_payment') }}</dt><dd>{{ Str::headline((string) ($order['payment_method'] ?? '—')) }}@if($invoiceConfig['showPaymentStatus'] ?? true) · {{ Str::headline((string) ($order['payment_status'] ?? '—')) }}@endif</dd>@endif
                    </dl>
                </div>
            </header>

            @if($invoiceSectionEnabled('addresses'))
            <section class="invoice-parties" style="order:{{ $invoiceSectionOrder('addresses') }}">
                <div>
                    <div class="invoice-label">{{ __('labels.billed_to') }}</div>
                    <div class="invoice-party-name">{{ $billingName }}</div>
                    <div class="invoice-muted">{{ $order['billing_address_1'] ?? $order['shipping_address_1'] }}</div>
                    <div class="invoice-muted">{{ collect([$order['billing_city'] ?? null, $order['billing_state'] ?? null, $order['billing_zip'] ?? null])->filter()->implode(', ') }}</div>
                    <div class="invoice-muted">{{ collect([$order['billing_country'] ?? null, $order['billing_phone'] ?? null])->filter()->implode(' · ') }}</div>
                </div>
                <div>
                    <div class="invoice-label">{{ __('labels.ship_to') }}</div>
                    <div class="invoice-party-name">{{ $order['shipping_name'] ?? '—' }}</div>
                    <div class="invoice-muted">{{ $order['shipping_address_1'] ?? '' }}</div>
                    <div class="invoice-muted">{{ collect([$order['shipping_city'] ?? null, $order['shipping_state'] ?? null, $order['shipping_zip'] ?? null])->filter()->implode(', ') }}</div>
                    <div class="invoice-muted">{{ collect([$order['shipping_country'] ?? null, $order['shipping_phone'] ?? null])->filter()->implode(' · ') }}</div>
                </div>
            </section>
            @endif

            @if($invoiceSectionEnabled('seller_details'))
            <section class="invoice-strip" style="order:{{ $invoiceSectionOrder('seller_details') }}">
                <div class="invoice-stat"><span>{{ __('labels.place_of_supply') }}</span><strong>{{ $order['shipping_state'] ?? '—' }}</strong></div>
                <div class="invoice-stat"><span>Seller ID</span><strong>#{{ $sellerId }}</strong></div>
                <div class="invoice-stat"><span>Currency</span><strong>{{ $order['currency_code'] ?? data_get($currency, 'code', '—') }}</strong></div>
                <div class="invoice-stat"><span>Facilitated by</span><strong>{{ $systemSettings['appName'] ?? 'HyperCommerce' }}</strong></div>
            </section>
            @endif

            @if($invoiceSectionEnabled('items'))
            <div class="invoice-table-wrap" style="order:{{ $invoiceSectionOrder('items') }}">
                <table class="invoice-table">
                    <thead><tr><th>{{ __('labels.item') }} details</th><th>{{ __('labels.qty') }}</th><th>{{ __('labels.unit_price') }}</th><th>Taxable</th><th>{{ __('labels.tax') }}</th><th>{{ __('labels.total') }}</th></tr></thead>
                    <tbody>
                    @foreach($vendor['items'] as $item)
                        @php
                            $addons = $item->orderItem?->addons ?? collect();
                            $quantity = max(1, (int) $item['quantity']);
                            $unitPrice = (float) $item['price'];
                            $taxPercent = (float) ($item['orderItem']['tax_percent'] ?? 0);
                            $lineTax = round($quantity * $unitPrice * $taxPercent / 100, 2);
                            $addonTotal = round($quantity * (float) $addons->sum('price'), 2);
                            $taxable = round(($quantity * $unitPrice) + $addonTotal, 2);
                            $lineTotal = round($taxable + $lineTax, 2);
                        @endphp
                        <tr>
                            <td>
                                <div class="invoice-item-title">{{ $item['orderItem']['title'] ?? $item['product']['title'] ?? 'Item' }}</div>
                                <div class="invoice-item-detail">{{ collect([$item['orderItem']['variant_title'] ?? $item['variant']['title'] ?? null, !empty($item['orderItem']['sku']) ? 'SKU: '.$item['orderItem']['sku'] : null, !empty($item['product']['hsn_code']) ? 'HSN: '.$item['product']['hsn_code'] : null])->filter()->implode(' · ') }}</div>
                                @if($invoiceConfig['showAddons'] ?? true)@foreach($addons as $addon)<div class="invoice-item-detail">+ {{ collect([$addon->addonGroup?->title, $addon->addonItem?->title])->filter()->implode(': ') }} · {{ formatOrderMoney((float) $addon->price, $currency) }}</div>@endforeach @endif
                            </td>
                            <td>{{ $quantity }}</td>
                            <td>{{ formatOrderMoney($unitPrice, $currency) }}</td>
                            <td>{{ formatOrderMoney($taxable, $currency) }}</td>
                            <td><span class="invoice-item-detail">{{ number_format($taxPercent, 2) }}%</span><br>{{ formatOrderMoney($lineTax, $currency) }}</td>
                            <td><strong>{{ formatOrderMoney($lineTotal, $currency) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if($invoiceSectionEnabled('totals'))
            <section class="invoice-bottom" style="order:{{ $invoiceSectionOrder('totals') }}">
                <div class="invoice-note">
                    <strong>Seller tax details</strong><br>
                    {{ __('labels.place_of_supply') }}: {{ $order['shipping_state'] ?? '—' }}<br>
                    @if(!empty($store['tax_number'])){{ $store['tax_name'] ?? 'GSTIN' }}: {{ $store['tax_number'] }}@endif
                </div>
                <div class="invoice-summary">
                    <div class="invoice-summary-row"><span>{{ __('labels.items_subtotal') }}</span><strong>{{ formatOrderMoney($sellerSubtotal, $currency) }}</strong></div>
                    @if($sellerDiscount > 0)<div class="invoice-summary-row"><span>{{ __('labels.promo_discount') }}</span><strong>- {{ formatOrderMoney($sellerDiscount, $currency) }}</strong></div>@endif
                    @if($sellerShipping > 0)<div class="invoice-summary-row"><span>{{ __('labels.shipping') }}</span><strong>{{ formatOrderMoney($sellerShipping, $currency) }}</strong></div>@endif
                    <div class="invoice-summary-row"><span>{{ __('labels.tax') }} ({{ __('labels.included') }})</span><strong>{{ formatOrderMoney($sellerTax, $currency) }}</strong></div>
                    <div class="invoice-grand-total"><span>{{ $invoiceConfig['totalLabel'] ?? __('labels.total') }}</span><span>{{ formatOrderMoney($sellerTotal, $currency) }}</span></div>
                </div>
            </section>
            @endif

            @if($invoiceSectionEnabled('signature'))
            <section class="invoice-signoff" style="order:{{ $invoiceSectionOrder('signature') }}">
                <div><div class="invoice-label">Declaration</div><div class="invoice-muted">The values shown are the original seller-order values captured when the order was placed. Returns and refunds do not rewrite this document.</div></div>
            </section>
            @endif

            @if($invoiceSectionEnabled('terms'))<div class="invoice-note" style="order:{{ $invoiceSectionOrder('terms') }}">{{ $invoiceConfig['terms'] ?? 'Please retain this invoice for your records.' }}</div>@endif
            @if($invoiceSectionEnabled('footer'))<footer class="invoice-footer" style="order:{{ $invoiceSectionOrder('footer') }}"><span>{{ $invoiceConfig['footer'] ?? __('labels.order').' #'.($order['order_number'] ?? $order['id']).' · via '.($systemSettings['appName'] ?? 'HyperCommerce') }}</span></footer>@endif
        </div>
    </article>
@endforeach
</body>
</html>
