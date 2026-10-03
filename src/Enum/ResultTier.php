<?php

declare(strict_types=1);

namespace App\Enum;

enum ResultTier: string
{
    case FIRST_STEPS = 'first_steps';
    case AWAKENING = 'awakening';
    case KEEN_NOSE = 'keen_nose';
    case MASTER = 'master';

    public static function fromAccuracy(float $accuracy): self
    {
        return match (true) {
            $accuracy >= 90 => self::MASTER,
            $accuracy >= 70 => self::KEEN_NOSE,
            $accuracy >= 40 => self::AWAKENING,
            default => self::FIRST_STEPS,
        };
    }

    public static function fromChainLength(int $chain): self
    {
        return match (true) {
            $chain >= 10 => self::MASTER,
            $chain >= 6 => self::KEEN_NOSE,
            $chain >= 3 => self::AWAKENING,
            default => self::FIRST_STEPS,
        };
    }

    public function label(): string
    {
        return 'ui.edu.result.tier.' . $this->value;
    }
}
