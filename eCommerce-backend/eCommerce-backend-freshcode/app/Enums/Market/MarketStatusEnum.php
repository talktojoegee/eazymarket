<?php

namespace App\Enums\Market;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static ACTIVE()
 * @method static INACTIVE()
 */
enum MarketStatusEnum: string
{
    use InvokableCases, Values, Names;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
