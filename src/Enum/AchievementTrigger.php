<?php

declare(strict_types=1);

namespace App\Enum;

enum AchievementTrigger: string
{
    case FIRST_MATCH = 'first_match';
    case N_MATCHES = 'n_matches';
    case N_SPICES_USED = 'n_spices_used';
    case FIRST_DISCOVERY = 'first_discovery';
    case N_FAVORITES = 'n_favorites';
}
