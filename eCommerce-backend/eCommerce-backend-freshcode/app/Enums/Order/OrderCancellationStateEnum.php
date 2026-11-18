<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Order-level summary of cancellation activity. Set by ClawbackService when
 * partial or full cancels happen.
 *
 *  - NONE     : no items cancelled
 *  - PARTIAL  : some items cancelled, order still has live items
 *  - FULL     : every item cancelled; order is effectively cancelled
 *
 * @method static NONE()
 * @method static PARTIAL()
 * @method static FULL()
 */
enum OrderCancellationStateEnum: string
{
    use InvokableCases, Values, Names;

    case NONE = 'NONE';
    case PARTIAL = 'PARTIAL';
    case FULL = 'FULL';
}
