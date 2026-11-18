<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static HOURS()
 * @method static DAYS()
 */
enum EtaUnitEnum: string
{
    use InvokableCases, Values, Names;

    case HOURS = 'hours';
    case DAYS = 'days';
}
