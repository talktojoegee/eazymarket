<?php

namespace App\Enums\Payment;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum FlutterwaveCurrencyCodeEnum: string
{
    use InvokableCases, Names, Values;

    case NGN = 'NGN';
    case GHS = 'GHS';
    case USD = 'USD';
    case GBP = 'GBP';
    case EUR = 'EUR';
    case KES = 'KES';
    case ZAR = 'ZAR';
    case XAF = 'XAF';
    case XOF = 'XOF';
    case UGX = 'UGX';
    case RWF = 'RWF';
    case TZS = 'TZS';
    case ZMW = 'ZMW';
    case XCD = 'XCD';
    case CAD = 'CAD';
    case GMD = 'GMD';
    case SLL = 'SLL';
    case MGA = 'MGA';
    case BWP = 'BWP';
    case EGP = 'EGP';
    case MAD = 'MAD';
    case GNF = 'GNF';
    case CDF = 'CDF';
    case LRD = 'LRD';
    case MWK = 'MWK';
    case SZL = 'SZL';
    case SCR = 'SCR';
    case MRU = 'MRU';
    case BIF = 'BIF';
    case KMF = 'KMF';
    case STN = 'STN';
    case LSL = 'LSL';
    case NAD = 'NAD';
}
