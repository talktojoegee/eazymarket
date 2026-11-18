<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Payment axis (Shopify financial_status parity). Additive cutover: legacy
 * 'completed' is kept (treat as synonymous with 'paid') while the Shopify values
 * are added alongside it.
 *
 * @method static PENDING()
 * @method static AUTHORIZED()
 * @method static PAID()
 * @method static PARTIALLY_PAID()
 * @method static COMPLETED()
 * @method static PARTIALLY_REFUNDED()
 * @method static REFUNDED()
 * @method static VOIDED()
 * @method static FAILED()
 */
enum PaymentStatusEnum: string
{
    use InvokableCases, Values, Names;

    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case PARTIALLY_PAID = 'partially_paid';
    case COMPLETED = 'completed';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';
    case VOIDED = 'voided';
    case FAILED = 'failed';
}
