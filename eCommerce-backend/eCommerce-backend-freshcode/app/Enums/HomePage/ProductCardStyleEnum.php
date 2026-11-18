<?php

namespace App\Enums\HomePage;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Card style for a web products section.
 * @method static STANDARD()
 * @method static SHOWCASE()
 * @method static COMPACT()
 * @method static MINIMAL()
 */
enum ProductCardStyleEnum: string
{
    use InvokableCases, Values, Names;

    case STANDARD = 'standard';
    case SHOWCASE = 'showcase';
    case COMPACT = 'compact';
    case MINIMAL = 'minimal';
}
