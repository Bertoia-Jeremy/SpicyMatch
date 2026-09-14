<?php

declare(strict_types=1);

namespace App\Message;

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
