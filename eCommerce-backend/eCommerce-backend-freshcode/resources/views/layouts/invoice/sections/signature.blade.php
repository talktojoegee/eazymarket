<section class="invoice-signature" data-invoice-section="signature" style="{{ $sectionStyle('signature') }}">
    @if(!empty($systemSettings['adminSignature']))<img src="{{ $systemSettings['adminSignature'] }}" alt="">@endif
    <strong>{{ __('labels.authorized_signature') }}</strong>
</section>
