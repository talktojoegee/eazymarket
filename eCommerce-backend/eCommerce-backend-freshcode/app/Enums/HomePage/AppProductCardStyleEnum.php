<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Product card styles available in the customer app home layout.
 *
 * @method static QUICK_ADD()
 * @method static CATALOG()
 */
enum AppProductCardStyleEnum: string
{
    use InvokableCases, Values, Names;

    case QUICK_ADD = 'quick_add';
    case CATALOG = 'catalog';
}
