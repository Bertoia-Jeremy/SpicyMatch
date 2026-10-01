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
    case ACTIVITY_STREAK = 'activity_streak';
    case EASTER_EGG_FOUND = 'easter_egg_found';
    case AROMATIC_GROUPS_VISITED = 'aromatic_groups_visited';
    case FIRST_GAME = 'first_game';
    case N_GAMES_COMPLETED = 'n_games_completed';
    case GAME_SCORE_THRESHOLD = 'game_score_threshold';
    case GAME_PERFECT_RUN = 'game_perfect_run';
    case GROUP_MASTERY_READ = 'group_mastery_read';
    case ALL_PREPARATION_METHODS_READ = 'all_preparation_methods_read';
    case FIRST_MANUAL_MATCH = 'first_manual_match';
    case ALL_CONTENT_KINDS_READ = 'all_content_kinds_read';
}
