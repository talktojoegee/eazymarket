<?php

namespace App\Enums\Payment;

enum RefundOutcome: string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case FAILED = 'failed';
}
