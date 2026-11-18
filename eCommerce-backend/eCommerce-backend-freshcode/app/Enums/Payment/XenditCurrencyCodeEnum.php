<?php

namespace App\Enums\Payment;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum XenditCurrencyCodeEnum: string
{
    use InvokableCases, Names, Values;

    case IDR = 'IDR';
    case PHP = 'PHP';
    case VND = 'VND';
    case THB = 'THB';
    case SGD = 'SGD';
    case MYR = 'MYR';
    case USD = 'USD';
}
