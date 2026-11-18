<?php

namespace App\Services\Shipping;

use App\Models\SellerOrder;

class ShipmentMoney
{
    public static function linesFor(SellerOrder $sellerOrder, array $lines): array
    {
        $soItems = $sellerOrder->items()->with('orderItem.product')->get()
            ->keyBy(fn ($item) => (int) $item->order_item_id);

        $perLine = [];
        $gross = 0.0;
        $discount = 0.0;

        foreach ($lines as $orderItemId => $qty) {
            $qty = (int) $qty;
            $item = $soItems->get((int) $orderItemId);
            $orderItem = $item?->orderItem;
            if (!$orderItem || $qty <= 0) {
                continue;
            }

            $orderedQty = max(1, (int) $orderItem->quantity);
            $unitGross = (float) $orderItem->subtotal / $orderedQty;
            $lineDiscount = (float) $orderItem->promo_discount * $qty / $orderedQty;

            $gross += $unitGross * $qty;
            $discount += $lineDiscount;

            $perLine[] = [
                'order_item_id' => (int) $orderItemId,
                'title'         => $orderItem->title ?? ('Item #' . $orderItemId),
                'sku'           => (string) ($item->product_variant_id ?? $item->product_id ?? $orderItemId),
                'hsn'           => preg_match('/^\d{4}(\d{2}(\d{2})?)?$/', (string) ($orderItem->product?->hsn_code ?? ''))
                    ? $orderItem->product->hsn_code
                    : null,
                'units'         => $qty,
                'unit_gross'    => round($unitGross, 4),
                'line_discount' => round($lineDiscount, 4),
            ];
        }

        return [
            'lines'    => $perLine,
            'gross'    => round($gross, 2),
            'discount' => round($discount, 2),
        ];
    }
}
