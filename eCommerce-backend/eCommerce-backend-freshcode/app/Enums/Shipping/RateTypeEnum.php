<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * How a shipping_zone computes its rate.
 *
 *  - CARRIER_QUOTED : Shiprocket prices it; zone has no rate config
 *  - FLAT           : single flat_rate, optional free_above
 *  - WEIGHT_BASED   : tiered by parcel weight (weight_rules JSON)
 *  - PRICE_BASED    : tiered by cart subtotal (price_rules JSON)
 *
 * @method static CARRIER_QUOTED()
 * @method static FLAT()
 * @method static WEIGHT_BASED()
 * @method static PRICE_BASED()
 */
enum RateTypeEnum: string
{
    use InvokableCases, Values, Names;

    case CARRIER_QUOTED = 'carrier_quoted';
    case FLAT = 'flat';
    case WEIGHT_BASED = 'weight_based';
    case PRICE_BASED = 'price_based';
}
