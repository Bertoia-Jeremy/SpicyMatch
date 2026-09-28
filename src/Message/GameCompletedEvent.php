<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GameCompletedEvent
{
    public function __construct(
        public int $userId,
        public int $sessionId,
        public string $gameMode,
        public int $correctAnswers,
        public int $totalQuestions,
        public int $xpEarned,
    ) {
    }
}
