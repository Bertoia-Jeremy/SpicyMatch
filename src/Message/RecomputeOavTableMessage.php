<?php

declare(strict_types=1);

namespace App\Message;

final class RecomputeOavTableMessage
{
    /**
     * @param string $reason raison du rebuild (pour les logs — max 255 chars)
     */
    public function __construct(
        public readonly string $reason = 'manual',
        public readonly int $attempt = 1,
    ) {
        if (trim($this->reason) === '') {
            throw new \InvalidArgumentException('RecomputeOavTableMessage::$reason must not be empty.');
        }

        if ($this->attempt < 1) {
            throw new \InvalidArgumentException('RecomputeOavTableMessage::$attempt must be greater than or equal to 1.');
        }
    }

    public function nextAttempt(): self
    {
        return new self($this->reason, $this->attempt + 1);
    }
}
