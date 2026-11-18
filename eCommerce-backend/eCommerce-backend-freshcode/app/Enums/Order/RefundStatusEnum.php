<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Lifecycle of a refund record.
 *
 *  - OWED   : recorded but the money hasn't moved yet. Default for gateway/
 *             manual refunds until the admin issues them.
 *  - ISSUED : money has actually gone back to the customer (wallet credited,
 *             gateway refund succeeded, manual cash handed over).
 *  - FAILED : terminal failure — gateway rejected or manual write-off rejected.
 *             Admin can retry by creating a new refund row.
 *
 * @method static OWED()
 * @method static ISSUED()
 * @method static FAILED()
 */
enum RefundStatusEnum: string
{
    use InvokableCases, Values, Names;

    case OWED = 'owed';
    case ISSUED = 'issued';
    case FAILED = 'failed';
}
