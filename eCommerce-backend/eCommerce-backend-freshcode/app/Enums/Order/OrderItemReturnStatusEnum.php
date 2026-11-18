<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Return lifecycle (Shopify-aligned).
 *
 *   requested → approved → in_transit → received → refunded → closed
 *            ↘ declined (closed, no refund)
 *            ↘ cancelled (customer abandoned)
 *
 * Manual pickup today (carrier hook can later drive in_transit / received).
 * Restock decision + fault are carried on order_item_returns (restock flag /
 * reason_type), not in this status.
 *
 * @method static REQUESTED()
 * @method static APPROVED()
 * @method static DECLINED()
 * @method static IN_TRANSIT()
 * @method static RECEIVED()
 * @method static REFUNDED()
 * @method static CLOSED()
 * @method static CANCELLED()
 */
enum OrderItemReturnStatusEnum: string
{
    use InvokableCases, Names, Values;

    case REQUESTED  = 'requested';
    case APPROVED   = 'approved';
    case DECLINED   = 'declined';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED   = 'received';
    case REFUNDED   = 'refunded';
    case CLOSED     = 'closed';
    case CANCELLED  = 'cancelled';
}
