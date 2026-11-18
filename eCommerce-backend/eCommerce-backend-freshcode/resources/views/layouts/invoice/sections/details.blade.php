<section class="invoice-details-section" data-invoice-section="details" style="{{ $sectionStyle('details') }}">
    <strong>Invoice details</strong>
    <div class="invoice-metadata">
        <span><strong>{{ __('labels.order') }} #</strong> {{ $order['order_number'] ?? $order['id'] }}</span>
        <span><strong>{{ __('labels.order_date') }}:</strong> {{ optional($order['created_at'])->format('Y-m-d H:i') }}</span>
    </div>
</section>
