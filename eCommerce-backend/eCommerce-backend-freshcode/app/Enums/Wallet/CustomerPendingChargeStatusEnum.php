<?php

namespace App\Enums\Wallet;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Lifecycle of a customer_pending_charges row.
 *
 *  - PENDING : the row is outstanding; CartService surfaces it at next checkout
 *  - APPLIED : the row was settled against a later order
 *  - EXPIRED : optional time-based write-off (Setting `pending_charges_expire_days` > 0)
 *  - WAIVED  : admin forgave the charge (audit-only)
 *
 * @method static PENDING()
 * @method static APPLIED()
 * @method static EXPIRED()
 * @method static WAIVED()
 */
enum CustomerPendingChargeStatusEnum: string
{
    use InvokableCases, Values, Names;

    case PENDING = 'pending';
    case APPLIED = 'applied';
    case EXPIRED = 'expired';
    case WAIVED = 'waived';
}
