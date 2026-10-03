<?php

declare(strict_types=1);

namespace App\Enum;

enum RecordState: string
{
    case FIRST = 'first';
    case NEW = 'new';
    case STANDING = 'standing';

    public static function resolve(int $score, ?int $previousBest): ?self
    {
        return match (true) {
            $previousBest === null => $score > 0 ? self::FIRST : null,
            $score > $previousBest => self::NEW,
            default => self::STANDING,
        };
    }

    public function isCelebrated(): bool
    {
        return $this !== self::STANDING;
    }

    public function label(): string
    {
        return 'ui.edu.result.record.' . $this->value;
    }
}
