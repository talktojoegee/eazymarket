<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Customer-facing order-item status codes — the shopper-friendly buckets the
 * customer app shows and filters by. These are DERIVED from the operational
 * axes (item bucket + fulfillment rollup + active returns), never stored: the
 * single source of truth for the derivation is
 * OrderCustomerStatusPresenter::itemStatus(). This enum lists the same codes so
 * the order-listing status filter and the settings `orderStatusEnum` payload
 * stay in lockstep with what the customer actually sees.
 *
 * @method static PROCESSING()
 * @method static SHIPPED()
 * @method static DELIVERED()
 * @method static ON_HOLD()
 * @method static RETURN_REQUESTED()
 * @method static RETURNED()
 * @method static REFUNDED()
 * @method static CANCELLED()
 */
enum CustomerItemStatusEnum: string
{
    use InvokableCases, Names, Values;

    case PROCESSING       = 'processing';
    case SHIPPED          = 'shipped';
    case DELIVERED        = 'delivered';
    case ON_HOLD          = 'on_hold';
    case RETURN_REQUESTED = 'return_requested';
    case RETURNED         = 'returned';
    case REFUNDED         = 'refunded';
    case CANCELLED        = 'cancelled';
}
