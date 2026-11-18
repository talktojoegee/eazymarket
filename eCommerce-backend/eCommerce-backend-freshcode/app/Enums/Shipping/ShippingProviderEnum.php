<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * The `code` value of a row in `shipping_providers`. Used as the discriminator
 * on shipments, shipping_zones, serviceability_cache, and shipping_webhook_events.
 *
 * Phase 7 may add Delhivery, Bluedart, DHL — add cases here and rows in the
 * shipping_providers table seed.
 *
 * @method static SHIPROCKET()
 * @method static MANUAL()
 * @method static LOCAL_DELIVERY()
 * @method static LOCAL_PICKUP()
 */
enum ShippingProviderEnum: string
{
    use InvokableCases, Values, Names;

    case SHIPROCKET = 'shiprocket';
    case MANUAL = 'manual';
    case LOCAL_DELIVERY = 'local_delivery';
    case LOCAL_PICKUP = 'local_pickup';
}
