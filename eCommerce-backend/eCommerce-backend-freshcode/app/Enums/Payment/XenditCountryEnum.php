<?php

namespace App\Enums\Payment;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

enum XenditCountryEnum: string
{
    use InvokableCases, Names, Values;

    case INDONESIA = 'ID';
    case PHILIPPINES = 'PH';
    case VIETNAM = 'VN';
    case THAILAND = 'TH';
    case SINGAPORE = 'SG';
    case MALAYSIA = 'MY';
}
