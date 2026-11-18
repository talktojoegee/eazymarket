<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Order lifecycle wrapper (Shopify-aligned). The detailed lifecycle now lives in
 * the independent axes — payment_status, fulfillment_status, cancellation_state
 * — NOT here. This only tracks which bucket the order is in:
 *
 *   OPEN      – active, still being worked
 *   ARCHIVED  – completed and filed away (no money/goods effect)
 *   CANCELLED – terminated (see orders.cancel_reason)
 *
 * @method static OPEN()
 * @method static ARCHIVED()
 * @method static CANCELLED()
 */
enum OrderStatusEnum: string
{
    use InvokableCases, Names, Values;

    case OPEN = 'open';
    case ARCHIVED = 'archived';
    case CANCELLED = 'cancelled';
}
