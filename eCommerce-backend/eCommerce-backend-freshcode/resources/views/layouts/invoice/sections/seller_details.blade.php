<section class="invoice-seller" data-invoice-section="seller_details" style="{{ $sectionStyle('seller_details') }}">
    <strong>{{ __('labels.sold_by') }}: {{ $sellerName }}</strong>
    <small>Seller ID: {{ $sellerOrder->first()->seller_id }}@if($sellerOrder->first()->items->first()?->orderItem?->store?->tax_number) · GSTIN: {{ $sellerOrder->first()->items->first()->orderItem->store->tax_number }}@endif</small>
</section>
