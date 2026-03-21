<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when a GameSession is finished.
 * Handled by GameGamificationHandler to update XP and unlock achievements.
 */
final class GameCompletedEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly int $sessionId,
        public readonly string $gameMode,
        public readonly int $correctAnswers,
        public readonly int $totalQuestions,
        public readonly int $xpEarned,
    ) {
    }
}
