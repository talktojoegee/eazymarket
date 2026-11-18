<?php

namespace App\Enums\Wallet;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Why a pending charge was created.
 *
 *  - SHIPPING_CLAWBACK : free-shipping subsidy lost on partial cancel
 *  - CANCELLATION_FEE  : seller-defined cancellation penalty (Phase 7)
 *  - OTHER             : generic catch-all; reason_note required
 *
 * @method static SHIPPING_CLAWBACK()
 * @method static CANCELLATION_FEE()
 * @method static OTHER()
 */
enum CustomerPendingChargeReasonEnum: string
{
    use InvokableCases, Values, Names;

    case SHIPPING_CLAWBACK = 'shipping_clawback';
    case CANCELLATION_FEE = 'cancellation_fee';
    case OTHER = 'other';
}
