<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * How one serviceability rule matches a destination.
 *
 *  - PINCODE_EXACT  : destination pincode equals `value`
 *  - PINCODE_PREFIX : destination pincode starts with `value` (e.g. "19" blocks 19xxxx)
 *  - PINCODE_RANGE  : destination pincode falls within `value`..`value_to` (numeric)
 *  - CITY           : destination city equals `value` (slug-normalized) — for
 *                     no-postcode countries (UAE, Hong Kong)
 *  - STATE          : destination state equals `value` (slug-normalized)
 *
 * @method static PINCODE_EXACT()
 * @method static PINCODE_PREFIX()
 * @method static PINCODE_RANGE()
 * @method static CITY()
 * @method static STATE()
 */
enum ServiceabilityMatchTypeEnum: string
{
    use InvokableCases, Values, Names;

    case PINCODE_EXACT = 'pincode_exact';
    case PINCODE_PREFIX = 'pincode_prefix';
    case PINCODE_RANGE = 'pincode_range';
    case CITY = 'city';
    case STATE = 'state';

    /** Match types that read the destination pincode. */
    public static function pincodeTypes(): array
    {
        return [self::PINCODE_EXACT(), self::PINCODE_PREFIX(), self::PINCODE_RANGE()];
    }
}
