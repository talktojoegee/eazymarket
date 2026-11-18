<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Who pays for a shipping leg.
 *
 *  - CUSTOMER  : forward shipment, customer pays at checkout (default)
 *  - SELLER    : reverse pickup on a seller-fault return (debited from settlement)
 *  - PLATFORM  : reverse pickup on a customer-fault Shiprocket return (admin absorbs)
 *
 * @method static CUSTOMER()
 * @method static SELLER()
 * @method static PLATFORM()
 */
enum ShippingCostPayerEnum: string
{
    use InvokableCases, Values, Names;

    case CUSTOMER = 'CUSTOMER';
    case SELLER = 'SELLER';
    case PLATFORM = 'PLATFORM';
}
