@php
    use Illuminate\Support\Str;

    $sellerOrder = $shipment->sellerOrder;
    $order       = $sellerOrder?->order;
    $location    = $sellerOrder?->storeLocation;
    $seller      = $sellerOrder?->seller;
    $fromName    = $seller?->store_name ?? $seller?->user?->name ?? config('app.name');
    $orderRef    = $order?->order_number ? ('#' . $order->order_number) : ($order?->id ? ('#' . $order->id) : ($order?->uuid ?? '—'));
    $carrier     = $shipment->manual_carrier_name ?: '—';
    $tracking    = $shipment->tracking_number;
    $weight      = $shipment->weight_kg;
    $placedAt    = $order?->created_at;
    $payType     = strtolower((string) ($order?->payment_method ?? '')) === 'cod'
        ? (__('labels.cod') ?? 'COD')
        : (__('labels.prepaid') ?? 'Prepaid');
    $units       = (int) $shipment->items->sum('quantity');
    $appName     = config('app.name');

    $fromAddr = trim(collect([
        $location?->address_1,
        $location?->address_2,
        trim(collect([$location?->city, $location?->state])->filter()->implode(', ')),
        $location?->country_name ?? $location?->country_iso2,
    ])->filter()->implode(', '));

    $toName  = $order?->shipping_name ?? $order?->billing_name ?? '—';
    $toLine1 = trim(collect([$order?->shipping_address_1, $order?->shipping_address_2])->filter()->implode(', '));
    $toLine2 = trim(collect([$order?->shipping_city, $order?->shipping_state, $order?->shipping_zip])->filter()->implode(', '));
    $toCountry = $order?->shipping_country;
    $toPhone = $order?->shipping_phone;

    $barcodeSvg = null;
    if ($tracking) {
        $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
        $barcodeSvg = $generator->getBarcode((string) $tracking, $generator::TYPE_CODE_128, 2, 100);
        $barcodeSvg = preg_replace('/^<\?xml.*?\?>\s*/s', '', $barcodeSvg);
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('labels.shipping_label') ?? 'Shipping label' }} · {{ $orderRef }}</title>
    <link href="{{ asset('assets/theme/css/tabler.css') }}" rel="stylesheet"/>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: #f1f3f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #16181d;
        }

        /* real CODE-128 barcode (picqer SVG) stretched to wrapper */
        .pd-bc { width:100%; }
        .pd-bc svg { display:block; width:100%; height:100%; }

        .pd-head  { background:#16181d; color:#fff; }
        .pd-dash  { border-bottom: 1px dashed #c8ccd1; }
        .pd-solid { border-bottom: 2px solid #16181d; }
        .pd-logo  { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; background:#fff; color:#16181d; font-weight:700; border-radius:4px; font-size:10px; }
        .pd-cap   { text-transform:uppercase; letter-spacing:.06em; font-size:9px; color:#8a93a0; }

        /* label shell — fixed physical sizes (96dpi ≈ in*96) */
        .pd-label { display:none; flex-direction:column; margin:0 auto 24px; background:#fff;
                    border:1px solid #d9dce1; box-shadow:0 1px 6px rgba(16,24,40,.10); overflow:hidden; }
        .pd-label.is-active { display:flex; }
        .pd-label[data-style="standard"] { width:384px; min-height:576px; }   /* 4 × 6 in */
        .pd-label[data-style="compact"]  { width:384px; height:192px; }        /* 4 × 2 in */
        .pd-label[data-style="wide"]     { width:576px; height:384px; }        /* 6 × 4 in */

        .pd-row    { display:flex; align-items:center; justify-content:space-between; }
        .pd-pad    { padding:10px 14px; }
        .pd-fill   { flex:1 1 auto; }

        .pd-toolbar { max-width:600px; margin:0 auto 24px; }
        .pd-btn { border:1px solid #d9dce1; background:#fff; color:#1a2233; font-size:13px; font-weight:500;
                  padding:7px 13px; cursor:pointer; }
        .pd-btn + .pd-btn { border-left:0; }
        .pd-btn.active { background:#16181d; color:#fff; border-color:#16181d; }
        .pd-btn-print { background:#206bc4; color:#fff; border:0; border-radius:7px; padding:8px 16px;
                        font-size:13px; font-weight:600; cursor:pointer; }

        @media print {
            @page { margin: 8mm; }
            html, body { background:#fff !important; padding:0 !important; margin:0 !important; }
            .pd-toolbar { display:none !important; }
            .pd-label { display:none !important; margin:0 !important; border:1px solid #16181d !important; box-shadow:none !important; }
            .pd-label.is-active { display:flex !important; page-break-inside:avoid; }
            /* force backgrounds (dark header + barcode) to actually print */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

    <div class="pd-toolbar pd-row">
        <div role="group" aria-label="Label style">
            <button type="button" class="pd-btn active" data-style-btn="standard">{{ __('labels.label_standard') ?? 'Standard 4×6' }}</button>
            <button type="button" class="pd-btn" data-style-btn="compact">{{ __('labels.label_compact') ?? 'Compact 4×2' }}</button>
            <button type="button" class="pd-btn" data-style-btn="wide">{{ __('labels.label_wide') ?? 'Wide 6×4' }}</button>
        </div>
        <button class="pd-btn-print" onclick="window.print()">{{ __('labels.print') ?? 'Print' }}</button>
    </div>

    <div class="pd-label is-active" data-style="standard">
        <div class="pd-head pd-row" style="padding:8px 14px;">
            <span style="display:inline-flex; align-items:center; gap:7px; font-weight:700; font-size:12.5px;">{{ $appName }}</span>
            <span class="pd-cap" style="color:#cfd4da;">{{ __('labels.fulfilled_via_marketplace') ?? 'Fulfilled via marketplace' }}</span>
        </div>
        <div class="pd-row pd-pad pd-solid">
            <span style="font-size:24px; font-weight:700; line-height:1;">{{ $carrier }}</span>
            <span style="text-align:right;"><span class="pd-cap d-block">{{ __('labels.service') ?? 'Service' }}</span><span style="font-weight:600; font-size:13px;">{{ $payType }}</span></span>
        </div>
        <div class="pd-pad pd-dash">
            <div class="pd-cap" style="margin-bottom:3px;">{{ __('labels.ship_from') ?? 'Ship from' }}</div>
            <div style="font-size:12.5px; line-height:1.45;"><strong>{{ $fromName }}</strong>{{ $location?->name ? ' · ' . $location->name : '' }}@if($fromAddr)<br>{{ $fromAddr }}@endif</div>
        </div>
        <div class="pd-pad pd-solid">
            <div class="pd-cap" style="margin-bottom:3px;">{{ __('labels.ship_to') ?? 'Ship to' }}</div>
            <div style="font-size:22px; font-weight:700; line-height:1.15;">{{ $toName }}</div>
            <div style="margin-top:4px; font-size:15px; line-height:1.4;">{{ $toLine1 }}@if($toLine2)<br>{{ $toLine2 }}@endif @if($toCountry)<br>{{ $toCountry }}@endif</div>
            @if($toPhone)<div style="margin-top:7px; font-size:13px; color:#5a6472;">{{ $toPhone }}</div>@endif
        </div>
        <div class="pd-pad pd-dash">
            <div class="pd-cap" style="margin-bottom:3px;">{{ __('labels.items') ?? 'Items' }} · {{ $units }} {{ __('labels.units') ?? 'units' }}</div>
            @foreach($shipment->items as $si)
                @php $oi = $si->orderItem; @endphp
                <div class="pd-row" style="font-size:12px; padding:2px 0;"><span>{{ $oi?->title ?? $oi?->product?->title ?? '—' }}@php $slipVariant = $oi?->variant_title ?? $oi?->variant?->title; @endphp@if($slipVariant) <span style="color:#8a93a0;">· {{ $slipVariant }}</span>@endif</span><span style="font-weight:600;">× {{ (int) $si->quantity }}</span></div>
            @endforeach
        </div>
        <div class="pd-fill pd-pad" style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
            @if($tracking)
                <div class="pd-bc" style="height:80px;">{!! $barcodeSvg !!}</div>
                <div style="margin-top:8px; font-weight:600; font-size:16px; letter-spacing:.16em;">{{ $tracking }}</div>
                <div class="pd-cap">{{ __('labels.tracking_number') ?? 'Tracking number' }}</div>
            @else
                <div style="color:#8a93a0; font-size:12px;">{{ __('labels.no_tracking_number') ?? 'No tracking number yet' }}</div>
            @endif
        </div>
        <div class="pd-row" style="padding:8px 14px; border-top:1px solid #e6e8eb; font-size:11.5px;">
            <span><strong>{{ __('labels.order') ?? 'Order' }} {{ $orderRef }}</strong> · {{ $units }} {{ __('labels.units') ?? 'units' }}@if($weight) · {{ $weight }} kg @endif</span>
            <span style="color:#8a93a0;">@if($placedAt){{ $placedAt->format('M j, Y') }}@endif</span>
        </div>
    </div>

    <div class="pd-label" data-style="compact">
        <div class="pd-head pd-row" style="padding:5px 14px;">
            <span style="display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size:11px;">{{ $appName }}</span>
            <span style="font-weight:700; font-size:12px;">{{ $carrier }} · {{ $payType }}</span>
        </div>
        <div class="pd-fill" style="display:flex;">
            <div class="pd-fill" style="display:flex; flex-direction:column; justify-content:center; padding:8px 14px; border-right:1px dashed #c8ccd1;">
                <div class="pd-cap" style="margin-bottom:3px;">{{ __('labels.ship_to') ?? 'Ship to' }}</div>
                <div style="font-size:17px; font-weight:700; line-height:1.15;">{{ $toName }}</div>
                <div style="margin-top:3px; font-size:11px; line-height:1.35;">{{ $toLine1 }}@if($toLine2)<br>{{ $toLine2 }}@endif</div>
            </div>
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:142px; padding:8px 12px; text-align:center;">
                @if($tracking)
                    <div class="pd-bc" style="height:48px;">{!! $barcodeSvg !!}</div>
                    <div style="margin-top:4px; font-weight:600; font-size:11px; letter-spacing:.08em;">{{ $tracking }}</div>
                @else
                    <div style="color:#8a93a0; font-size:10px;">{{ __('labels.no_tracking_number') ?? 'No tracking yet' }}</div>
                @endif
            </div>
        </div>
        <div class="pd-row" style="padding:5px 14px; border-top:1px solid #e6e8eb; font-size:10px;">
            <span><strong>{{ __('labels.order') ?? 'Order' }} {{ $orderRef }}</strong> · {{ $units }} {{ __('labels.units') ?? 'units' }}@if($weight) · {{ $weight }} kg @endif</span>
            <span style="color:#8a93a0;">@if($placedAt){{ $placedAt->format('M j, Y') }}@endif</span>
        </div>
    </div>

    <div class="pd-label" data-style="wide">
        <div class="pd-head pd-row" style="padding:8px 14px;">
            <span style="display:inline-flex; align-items:center; gap:7px; font-weight:700; font-size:13px;">{{ $appName }}</span>
            <span class="pd-cap" style="color:#cfd4da;">{{ __('labels.fulfilled_via_marketplace') ?? 'Fulfilled via marketplace' }}</span>
        </div>
        <div class="pd-row pd-pad pd-solid">
            <span style="font-size:24px; font-weight:700; line-height:1;">{{ $carrier }}</span>
            <span style="text-align:right;"><span class="pd-cap d-block">{{ __('labels.service') ?? 'Service' }}</span><span style="font-weight:600; font-size:13px;">{{ $payType }}</span></span>
        </div>
        <div class="pd-fill" style="display:flex;">
            <div class="pd-fill" style="display:flex; flex-direction:column; border-right:2px solid #16181d;">
                <div class="pd-pad pd-dash">
                    <div class="pd-cap" style="margin-bottom:3px;">{{ __('labels.ship_from') ?? 'Ship from' }}</div>
                    <div style="font-size:11.5px; line-height:1.4;"><strong>{{ $fromName }}</strong>{{ $location?->name ? ' · ' . $location->name : '' }}{{ $fromAddr ? ', ' . $fromAddr : '' }}</div>
                </div>
                <div class="pd-fill pd-pad" style="display:flex; flex-direction:column; justify-content:center;">
                    <div class="pd-cap" style="margin-bottom:3px;">{{ __('labels.ship_to') ?? 'Ship to' }}</div>
                    <div style="font-size:22px; font-weight:700; line-height:1.15;">{{ $toName }}</div>
                    <div style="margin-top:4px; font-size:14px; line-height:1.4;">{{ $toLine1 }}@if($toLine2)<br>{{ $toLine2 }}@endif @if($toCountry)<br>{{ $toCountry }}@endif</div>
                    @if($toPhone)<div style="margin-top:7px; font-size:12px; color:#5a6472;">{{ $toPhone }}</div>@endif
                </div>
            </div>
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:220px; padding:14px; text-align:center;">
                @if($tracking)
                    <div class="pd-bc" style="height:90px;">{!! $barcodeSvg !!}</div>
                    <div style="margin-top:8px; font-weight:600; font-size:15px; letter-spacing:.14em;">{{ $tracking }}</div>
                    <div class="pd-cap">{{ __('labels.tracking_number') ?? 'Tracking number' }}</div>
                @else
                    <div style="color:#8a93a0; font-size:12px;">{{ __('labels.no_tracking_number') ?? 'No tracking number yet' }}</div>
                @endif
            </div>
        </div>
        <div class="pd-row" style="padding:8px 14px; border-top:1px solid #e6e8eb; font-size:11.5px;">
            <span><strong>{{ __('labels.order') ?? 'Order' }} {{ $orderRef }}</strong> · {{ $units }} {{ __('labels.units') ?? 'units' }}@if($weight) · {{ $weight }} kg @endif</span>
            <span style="color:#8a93a0;">@if($placedAt){{ $placedAt->format('M j, Y') }}@endif</span>
        </div>
    </div>

    <script>
        (function () {
            var buttons = document.querySelectorAll('[data-style-btn]');
            var labels  = document.querySelectorAll('.pd-label');
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var style = btn.getAttribute('data-style-btn');
                    buttons.forEach(function (b) { b.classList.toggle('active', b === btn); });
                    labels.forEach(function (l) {
                        l.classList.toggle('is-active', l.getAttribute('data-style') === style);
                    });
                });
            });
        })();
    </script>
</body>
</html>
