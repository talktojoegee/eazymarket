@php
    use App\Enums\PaymentStatusEnum;
    use Illuminate\Support\Str;

    $baseOrder = $order['order'] ?? [];
    $order['currency'] = $baseOrder['currency'] ?? null;

    $paymentStatusValue = strtolower((string) ($baseOrder['payment_status'] ?? ''));
    $paymentStatusEnum = PaymentStatusEnum::tryFrom($paymentStatusValue);
    $paymentBadgeClass = paymentStatusBadgeClass($paymentStatusValue);

    $paymentIcon = match ($paymentStatusEnum) {
        PaymentStatusEnum::COMPLETED => 'ti ti-check',
        PaymentStatusEnum::AUTHORIZED => 'ti ti-lock',
        PaymentStatusEnum::PARTIALLY_PAID => 'ti ti-coins',
        PaymentStatusEnum::PENDING => 'ti ti-clock',
        PaymentStatusEnum::PARTIALLY_REFUNDED => 'ti ti-receipt-refund',
        PaymentStatusEnum::REFUNDED => 'ti ti-arrow-back-up',
        PaymentStatusEnum::VOIDED => 'ti ti-ban',
        PaymentStatusEnum::FAILED => 'ti ti-alert-circle',
        default => 'ti ti-help',
    };

    $sellerStatusValue = strtolower((string) ($order['fulfillment_status'] ?? 'unfulfilled'));
    $sellerOrderTerminal = ($sellerStatusValue === 'fulfilled') || (($baseOrder['status'] ?? '') === 'cancelled');
    $bulkEnabled = !$sellerOrderTerminal && ($editPermission ?? false);

    $panel = $panel ?? 'seller';
    $isSeller = $isSeller ?? true;
@endphp

@extends('layouts.seller.app', ['page' => $menuSeller['orders']['active'] ?? ""])

@section('title', __('labels.order_details'))

@section('header_data')
    @php
        $page_title = __('labels.order_details');
        $page_pretitle = __('labels.seller') . " " . __('labels.order_details');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.orders'), 'url' => route('seller.orders.index')],
        ['title' => __('labels.order_details'), 'url' => ''],
    ];
@endphp

@section('seller-content')
    @include('orders.partials._detail')
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/order.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/seller-orders.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/fulfillment.js') }}" defer></script>
@endpush
