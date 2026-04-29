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
    case GAME_SCORE_THRESHOLD = 'game_score_threshold';
    case GAME_PERFECT_RUN = 'game_perfect_run';
    case GROUP_MASTERY_READ = 'group_mastery_read';
    case N_UNIQUE_SPICES_USED_IN_GAMES = 'n_unique_spices_used_in_games';
    case ALL_PREPARATION_METHODS_READ = 'all_preparation_methods_read';
}
