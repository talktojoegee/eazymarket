<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Coarse per-line lifecycle (Shopify-aligned). Acceptance, fulfillment, and
 * payment each live on their own axis now:
 *   - fulfillment → order_items.fulfillment_status (FulfillmentStatusEnum)
 *   - shipment    → shipments.status (ShipmentStatusEnum)
 *   - payment     → orders.payment_status (PaymentStatusEnum)
 *
 * This enum is only the line's overall bucket:
 *   ACTIVE    – live line (accepted/fulfilling/delivered all read from the axes)
 *   CANCELLED – line cancelled pre-shipment (see order_items.cancellation_reason)
 *   RETURNED  – line returned post-delivery
 *   REFUNDED  – line refunded
 *
 * @method static ACTIVE()
 * @method static CANCELLED()
 * @method static RETURNED()
 * @method static REFUNDED()
 */
enum OrderItemStatusEnum: string
{
    use InvokableCases, Names, Values;

    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';
    case REFUNDED = 'refunded';
}
