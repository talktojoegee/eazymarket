<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * How a refund will be / was returned to the customer.
 *
 *  - WALLET  : credited to the customer's wallet — instant, off-gateway.
 *              Today's default for admin/seller/customer cancel flows.
 *  - GATEWAY : reversed through the payment gateway (Razorpay, Stripe, etc.).
 *              Recorded as `owed` until the gateway call succeeds.
 *  - MANUAL  : settled off-platform (cash, bank transfer). Admin records it
 *              after the fact with a note.
 *
 * @method static WALLET()
 * @method static GATEWAY()
 * @method static MANUAL()
 */
enum RefundMethodEnum: string
{
    use InvokableCases, Values, Names;

    case WALLET = 'wallet';
    case GATEWAY = 'gateway';
    case MANUAL = 'manual';
}
