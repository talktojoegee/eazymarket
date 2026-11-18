<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Reason an order or line item was cancelled (Shopify `cancel_reason` parity).
 * Recorded on orders.cancel_reason (and free-text detail on
 * order_items.cancellation_reason).
 *
 *   CUSTOMER  – customer changed their mind / requested it
 *   FRAUD     – fraudulent or high-risk order
 *   INVENTORY – items unavailable / out of stock
 *   DECLINED  – payment declined
 *   STAFF     – staff / seller error
 *   OTHER     – anything else (see free-text note)
 *
 * @method static CUSTOMER()
 * @method static FRAUD()
 * @method static INVENTORY()
 * @method static DECLINED()
 * @method static STAFF()
 * @method static OTHER()
 */
enum CancelReasonEnum: string
{
    use InvokableCases, Names, Values;

    case CUSTOMER  = 'customer';
    case FRAUD     = 'fraud';
    case INVENTORY = 'inventory';
    case DECLINED  = 'declined';
    case STAFF     = 'staff';
    case OTHER     = 'other';
}
