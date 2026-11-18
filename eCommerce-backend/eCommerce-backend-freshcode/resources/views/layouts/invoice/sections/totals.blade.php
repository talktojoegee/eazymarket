<section class="invoice-summary" data-invoice-section="totals" style="{{ $sectionStyle('totals') }}">
    <div class="invoice-summary-row"><span>{{ __('labels.items_subtotal') }}</span><strong>{{ formatOrderMoney($itemsSubtotal, $currency) }}</strong></div>
    @if($promoDiscount > 0)<div class="invoice-summary-row"><span>{{ __('labels.promo_discount') }}</span><strong>- {{ formatOrderMoney($promoDiscount, $currency) }}</strong></div>@endif
    @if($shippingTotal > 0)<div class="invoice-summary-row"><span>{{ __('labels.shipping') }}</span><strong>{{ formatOrderMoney($shippingTotal, $currency) }}</strong></div>@endif
    @if($orderTotal !== $payableTotal)<div class="invoice-summary-row"><span>{{ __('labels.total') }}</span><strong>{{ formatOrderMoney($orderTotal, $currency) }}</strong></div>@endif
    <div class="invoice-summary-row invoice-summary-row--total"><span>{{ $invoiceConfig['totalLabel'] ?? __('labels.total_payable') }}</span><strong>{{ formatOrderMoney($payableTotal, $currency) }}</strong></div>
</section>
