<?php

declare(strict_types=1);

namespace App\Enum;

enum HistoryTipKind: string
{
    case COOKING = 'cooking';
    case PREPARATION = 'preparation';
}
