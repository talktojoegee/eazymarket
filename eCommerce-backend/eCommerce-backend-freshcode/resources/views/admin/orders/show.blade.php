@php
    use App\Enums\Order\OrderItemStatusEnum;use App\Enums\Order\OrderStatusEnum;
    use App\Enums\Order\RefundStatusEnum;use App\Enums\PaymentStatusEnum;use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp
@php
    $paymentStatusValue = strtolower((string) ($order['payment_status'] ?? ''));
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
@endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['orders']['active'] ?? ""])
@section('title', __('labels.order_details'))

@section('header_data')
    @php
        $page_title = __('labels.order_details');
        $page_pretitle = __('labels.admin') . " " . __('labels.order_details');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.orders'), 'url' => route('admin.orders.index')],
        ['title' => __('labels.order_details'), 'url' => '']
    ];
    $panel = $panel ?? 'admin';
    $isSeller = $isSeller ?? false;
@endphp

@section('admin-content')
    @include('orders.partials._detail')
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/order.js') }}"></script>
    @if($editPermission ?? false)
        <script>
            window.adminOrderEndpoints = {
                addNote: '{{ url('admin/orders') }}/{{ $order['id'] ?? '' }}/note',
                markPaymentReceived: '{{ url('admin/orders') }}/{{ $order['id'] ?? '' }}/mark-payment-received',
            };
        </script>
        <script src="{{ hyperAsset('assets/js/admin-order-notes.js') }}" defer></script>
        <script src="{{ hyperAsset('assets/js/admin-orders.js') }}" defer></script>
        <script src="{{ hyperAsset('assets/js/fulfillment.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const reopenBtn = document.getElementById('btnReopenOrder');
                if (!reopenBtn) return;

                reopenBtn.addEventListener('click', async function () {
                    const url = reopenBtn.dataset.reopenUrl;
                    const confirmed = window.Swal
                        ? (await Swal.fire({
                            title: @json(__('labels.reopen_order')),
                            text: @json(__('labels.reopen_order_confirm')),
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: @json(__('labels.reopen_order')),
                        })).isConfirmed
                        : window.confirm(@json(__('labels.reopen_order_confirm')));
                    if (!confirmed) return;

                    reopenBtn.disabled = true;
                    try {
                        const res = await axios.post(url);
                        const ok = res.data?.success ?? false;
                        if (window.Toast) {
                            window.Toast.fire({ icon: ok ? 'success' : 'error', title: res.data?.message ?? '' });
                        }
                        if (ok) {
                            setTimeout(() => window.location.reload(), 700);
                            return;
                        }
                    } catch (e) {
                        const msg = e.response?.data?.message ?? @json(__('labels.something_went_wrong'));
                        if (window.Toast) window.Toast.fire({ icon: 'error', title: msg });
                    }
                    reopenBtn.disabled = false;
                });
            });
        </script>
    @endif
@endpush
