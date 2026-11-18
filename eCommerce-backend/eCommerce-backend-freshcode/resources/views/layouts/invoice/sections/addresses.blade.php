<section class="invoice-parties" data-invoice-section="addresses" style="{{ $sectionStyle('addresses') }}">
    <div class="invoice-detail-card">
        <strong>Billing information</strong>
        <span>{{ $billingName }}</span>
        <span>{{ $billingAddress ?: '—' }}</span>
        <span>{{ $billingContact ?: '—' }}</span>
    </div>
</section>
