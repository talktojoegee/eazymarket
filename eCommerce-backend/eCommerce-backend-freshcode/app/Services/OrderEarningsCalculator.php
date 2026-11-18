<?php

namespace App\Services;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Models\Order;
use App\Models\SellerOrder;

/**
 * Read-only earnings & settlement breakdown for an order.
 *
 * DISPLAY ONLY — this performs NO wallet, statement, or settlement side effects.
 * It just derives the numbers from fields already snapshotted on the order so the
 * admin can see "who earns what" on the order detail page. Actual settlement
 * (statement -> wallet, COD collection, FX to base currency) is deferred.
 *
 * All amounts are returned in the ORDER's currency (market currency), matching
 * the rest of the order detail page. Conversion to base currency happens later,
 * at settlement time, and is intentionally not done here.
 *
 * Money model (confirmed):
 *   - `subtotal` (per item) is TAX-INCLUSIVE; the tax rides inside it and is
 *     pass-through to the seller (the seller remits it), so the seller keeps it.
 *   - `admin_commission_amount` is charged on the PRE-TAX base.
 *   - seller line earning = subtotal - admin_commission_amount.
 *   - shipping: only MANUAL fulfilment is live today; on manual the seller keeps
 *     the shipping charge.
 *   - COD: the seller collects all the cash, so the seller OWES the platform its
 *     cut (commission, plus shipping only if the platform were the one to keep
 *     it). Prepaid: the platform holds the cash, so it OWES the seller the net.
 */
class OrderEarningsCalculator
{
    public const DIR_PLATFORM_OWES_SELLER = 'platform_owes_seller';
    public const DIR_SELLER_OWES_PLATFORM = 'seller_owes_platform';

    /**
     * @return array{
     *     currency_code:string, payment_method:string, is_cod:bool,
     *     seller_orders:array<int,array<string,mixed>>,
     *     totals:array<string,float>, settled:bool
     * }
     */
    public function forOrder(Order $order): array
    {
        $isCod = strtolower((string) $order->payment_method) === (string) PaymentTypeEnum::COD();

        $codCapable = array_map('strval', FulfillmentMethodEnum::codCapable());

        $platformFee = (float) $order->platform_fee;
        $codFee      = (float) $order->cod_fee;

        $sellerOrders = SellerOrder::with(['seller.user', 'items.orderItem'])
            ->where('order_id', $order->id)
            ->get();

        $groups = [];
        $tItemsSubtotal = $tTax = $tCommission = $tShipping = $tSellerNet = 0.0;

        foreach ($sellerOrders as $so) {
            $isManual = (string) $so->fulfillment_method === (string) FulfillmentMethodEnum::MANUAL();

            $items = [];
            $itemsSubtotal = $tax = $commission = 0.0;
            $hasCounted = false;

            foreach ($so->items as $soi) {
                $oi = $soi->orderItem;
                if (! $oi) {
                    continue;
                }

                $sub  = (float) $oi->subtotal;
                $comm = (float) $oi->admin_commission_amount;
                $itx  = (float) $oi->tax_amount;
                $earn = round($sub - $comm, 2);

                $counted = (string) $oi->status === (string) OrderItemStatusEnum::ACTIVE();

                $preTaxBase = round($sub - $itx, 2);
                $rate = $preTaxBase > 0 ? round($comm / $preTaxBase * 100, 2) : 0.0;

                $items[] = [
                    'title'           => $oi->title ?? '—',
                    'variant'         => $oi->variant_title,
                    'quantity'        => (int) $oi->quantity,
                    'status'          => (string) $oi->status,
                    'is_counted'      => $counted,
                    'subtotal'        => round($sub, 2),
                    'tax'             => round($itx, 2),
                    'commission'      => round($comm, 2),
                    'commission_rate' => $rate,
                    'pre_tax_base'    => $preTaxBase,
                    'line_earning'    => $counted ? $earn : 0.0,
                ];

                if ($counted) {
                    $itemsSubtotal += $sub;
                    $tax           += $itx;
                    $commission    += $comm;
                    $hasCounted     = true;
                }
            }

            $shipping         = (float) $so->shipping_charge_applied;
            $shippingToSeller = ($isManual && $hasCounted) ? $shipping : 0.0;
            $platformShipping = (! $isManual && $hasCounted) ? $shipping : 0.0;

            $sellerNet    = round($itemsSubtotal - $commission + $shippingToSeller, 2);
            $platformTake = round($commission + $platformShipping, 2);

            $platformCollects = in_array((string) $so->fulfillment_method, $codCapable, true);
            $sellerHoldsCash  = $isCod && ! $platformCollects;

            if ($sellerHoldsCash) {
                $direction        = self::DIR_SELLER_OWES_PLATFORM;
                $settlementAmount = $platformTake;
            } else {
                $direction        = self::DIR_PLATFORM_OWES_SELLER;
                $settlementAmount = $sellerNet;
            }

            $groups[] = [
                'seller_order_id'    => (int) $so->id,
                'seller_id'          => (int) $so->seller_id,
                'seller_name'        => $so->seller?->user?->name ?? '—',
                'fulfillment_method' => (string) $so->fulfillment_method,
                'fulfillment_status' => (string) $so->fulfillment_status,
                'is_manual'          => $isManual,
                'items'              => $items,
                'items_subtotal'     => round($itemsSubtotal, 2),
                'tax_total'          => round($tax, 2),
                'commission_total'   => round($commission, 2),
                'shipping_charge'    => round($shipping, 2),
                'shipping_to_seller' => round($shippingToSeller, 2),
                'seller_net'         => $sellerNet,
                'platform_take'      => $platformTake,
                'direction'          => $direction,
                'settlement_amount'  => round($settlementAmount, 2),
            ];

            $tItemsSubtotal += $itemsSubtotal;
            $tTax           += $tax;
            $tCommission    += $commission;
            $tShipping      += $shipping;
            $tSellerNet     += $sellerNet;
        }

        $platformTakeTotal = round($tCommission + $platformFee + $codFee, 2);

        $rollup = app(FulfillmentRollupService::class);
        $estimate = $rollup->estimatedArchiveDate($order);
        $isArchived = (string) $order->status === (string) OrderStatusEnum::ARCHIVED();

        return [
            'currency_code'  => (string) $order->currency_code,
            'payment_method' => (string) $order->payment_method,
            'is_cod'         => $isCod,
            'seller_orders'  => $groups,
            'totals'         => [
                'items_subtotal'   => round($tItemsSubtotal, 2),
                'tax_total'        => round($tTax, 2),
                'commission_total' => round($tCommission, 2),
                'shipping_total'   => round($tShipping, 2),
                'seller_net'       => round($tSellerNet, 2),
                'platform_fee'     => round($platformFee, 2),
                'cod_fee'          => round($codFee, 2),
                'platform_take'    => $platformTakeTotal,
            ],
            'archive'        => [
                'status'         => (string) $order->status,
                'is_archived'    => $isArchived,
                'resolved_at'    => $order->resolved_at?->format('Y-m-d H:i:s'),
                'grace_days'     => $rollup->graceDays(),
                'estimated_date' => $estimate?->format('Y-m-d'),
                'eligible_now'   => $estimate !== null && ! $isArchived && now()->greaterThanOrEqualTo($estimate),
            ],
            'settled'        => false,
        ];
    }

    /**
     * Seller-scoped view of the earnings: only the given seller order's group, with
     * platform internals (platform fee / COD fee / platform take) stripped out — the
     * seller sees their subtotal, commission and net earning, plus settlement direction.
     * `is_seller_view` lets the shared blade hide the platform-only cards.
     */
    public function forSellerOrder(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        if (! $order) {
            return ['currency_code' => '', 'is_seller_view' => true, 'seller_orders' => [], 'totals' => [], 'archive' => null, 'settled' => false];
        }

        $full = $this->forOrder($order);

        $group = collect($full['seller_orders'] ?? [])
            ->first(fn ($g) => (int) ($g['seller_order_id'] ?? 0) === (int) $sellerOrder->id);

        $groups = $group ? [$group] : [];

        return [
            'currency_code'  => $full['currency_code'] ?? (string) $order->currency_code,
            'payment_method' => $full['payment_method'] ?? (string) $order->payment_method,
            'is_cod'         => $full['is_cod'] ?? false,
            'is_seller_view' => true,
            'seller_orders'  => $groups,
            'totals'         => [
                'items_subtotal'   => (float) ($group['items_subtotal'] ?? 0),
                'tax_total'        => (float) ($group['tax_total'] ?? 0),
                'commission_total' => (float) ($group['commission_total'] ?? 0),
                'shipping_total'   => (float) ($group['shipping_to_seller'] ?? 0),
                'seller_net'       => (float) ($group['seller_net'] ?? 0),
            ],
            'archive'        => $full['archive'] ?? null,
            'settled'        => false,
        ];
    }
}
