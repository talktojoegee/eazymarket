<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * How a manual shipping_zone restricts where it delivers, evaluated against the
 * destination's pincode / city / state (see shipping_zone_serviceability rows).
 *
 *  - NONE  : no restriction; the zone serves every destination it geographically covers
 *  - DENY  : serve everywhere EXCEPT destinations matching a rule (blocklist)
 *  - ALLOW : serve ONLY destinations matching a rule (allowlist)
 *
 * Only consulted for MANUAL zones — carrier methods (Shiprocket) get
 * serviceability from the courier API, so this is ignored there.
 *
 * @method static NONE()
 * @method static DENY()
 * @method static ALLOW()
 */
enum ServiceabilityModeEnum: string
{
    use InvokableCases, Values, Names;

    case NONE = 'none';
    case DENY = 'deny';
    case ALLOW = 'allow';
}
