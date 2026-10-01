<?php

declare(strict_types=1);

namespace App\Enum;

enum ContentKind: string
{
    case SPICE = 'spice';
    case COMPOUND = 'compound';
    case FLAVOR = 'flavor';
}
