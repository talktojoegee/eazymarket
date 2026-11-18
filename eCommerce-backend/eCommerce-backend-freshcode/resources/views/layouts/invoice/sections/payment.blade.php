@if($invoiceConfig['showPaymentStatus'] ?? true)
    <section class="invoice-payment" data-invoice-section="payment" style="{{ $sectionStyle('payment') }}">{{ __('labels.menu_payment') }}: {{ Str::headline((string) ($order['payment_method'] ?? '—')) }} · {{ Str::headline((string) ($order['payment_status'] ?? '—')) }}</section>
@endif
