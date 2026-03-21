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
    case SPICE_READ = 'spice_read';
    case READING_STREAK = 'reading_streak';
    case EASTER_EGG_FOUND = 'easter_egg_found';
    case ALL_TERPENES_VISITED = 'all_terpenes_visited';
    case FIRST_GAME = 'first_game';
    case N_GAMES_COMPLETED = 'n_games_completed';
}
