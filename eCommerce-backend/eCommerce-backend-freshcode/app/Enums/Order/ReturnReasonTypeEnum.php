<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Drives the return cost-responsibility matrix:
 *
 *   method × reason_type → who pays shipping
 *
 *   Shiprocket     × SELLER_FAULT   → seller
 *   Shiprocket     × CUSTOMER_FAULT → platform absorbs
 *   Manual         × *              → seller absorbs (settings or seller-config; was markets column, dropped)
 *   Local delivery × *              → seller absorbs (own fleet)
 *   Local pickup   × *              → free (customer returns to store location)
 *
 * @method static SELLER_FAULT()
 * @method static CUSTOMER_FAULT()
 */
enum ReturnReasonTypeEnum: string
{
    use InvokableCases, Values, Names;

    case SELLER_FAULT = 'SELLER_FAULT';
    case CUSTOMER_FAULT = 'CUSTOMER_FAULT';
}
