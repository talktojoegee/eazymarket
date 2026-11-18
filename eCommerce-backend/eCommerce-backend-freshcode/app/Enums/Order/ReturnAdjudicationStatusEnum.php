<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Seller-vs-customer dispute on a return reason.
 *
 *  - NONE      : no dispute, seller accepted the customer's reason
 *  - PENDING   : seller disputed the classification; awaiting admin decision
 *  - RESOLVED  : admin made a call (recorded in admin_notes)
 *
 * @method static NONE()
 * @method static PENDING()
 * @method static RESOLVED()
 */
enum ReturnAdjudicationStatusEnum: string
{
    use InvokableCases, Values, Names;

    case NONE = 'none';
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
}
