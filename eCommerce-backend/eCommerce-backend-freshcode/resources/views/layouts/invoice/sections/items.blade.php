<section class="invoice-items" data-invoice-section="items" style="{{ $sectionStyle('items') }}">
    <h2>{{ __('labels.order_summary') }}</h2>
    <div class="invoice-table-wrap">
        <table class="invoice-table">
            <thead><tr><th>{{ __('labels.item') }}</th><th>{{ __('labels.qty') }}</th>@if($invoiceConfig['showItemStatus'] ?? true)<th>{{ __('labels.status') }}</th>@endif<th>{{ __('labels.tax') }}</th><th>{{ __('labels.unit_price') }}</th><th>{{ __('labels.total') }}</th></tr></thead>
            <tbody>
                @foreach($sellerOrder as $vendor)
                    @foreach($vendor->items as $item)
                        @php
                            $orderItem = $item->orderItem;
                            $addons = $orderItem?->addons ?? collect();
                            $quantity = max(1, (int) $item->quantity);
                            $unitPrice = (float) $item->price;
                            $taxPercent = (float) ($orderItem?->tax_percent ?? 0);
                            $lineTax = round($quantity * $unitPrice * $taxPercent / 100, 2);
                            $addonTotal = round($quantity * (float) $addons->sum('price'), 2);
                            $lineTotal = round(($quantity * $unitPrice) + $addonTotal + $lineTax, 2);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $orderItem?->title ?? $item->product?->title ?? __('labels.item') }}</strong>
                                <small>{{ collect([$orderItem?->variant_title ?: $item->variant?->title, $orderItem?->sku ? 'SKU: '.$orderItem->sku : null])->filter()->implode(' · ') }}</small>
                                @if($invoiceConfig['showAddons'] ?? true)
                                    @foreach($addons as $addon)<small>+ {{ collect([$addon->addonGroup?->title, $addon->addonItem?->title])->filter()->implode(': ') }} · {{ formatOrderMoney((float) $addon->price, $currency) }}</small>@endforeach
                                @endif
                            </td>
                            <td>{{ $quantity }}</td>
                            @if($invoiceConfig['showItemStatus'] ?? true)<td>{{ Str::headline((string) ($item->status ?? '—')) }}</td>@endif
                            <td>{{ formatOrderMoney($lineTax, $currency) }} <small>({{ number_format($taxPercent, 2) }}%)</small></td>
                            <td>{{ formatOrderMoney($unitPrice, $currency) }}</td>
                            <td><strong>{{ formatOrderMoney($lineTotal, $currency) }}</strong></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</section>
