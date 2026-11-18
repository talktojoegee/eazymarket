<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PENDING()
 * @method static REGISTERED()
 * @method static FAILED()
 */
enum ShiprocketSyncStatusEnum: string
{
    use InvokableCases, Values, Names;

    case PENDING    = 'pending';
    case REGISTERED = 'registered';
    case FAILED     = 'failed';
}
