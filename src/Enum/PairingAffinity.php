<?php

declare(strict_types=1);

namespace App\Enum;

enum PairingAffinity: string
{
    case EXCELLENT = 'excellent';
    case HARMONIEUX = 'harmonieux';
    case AUDACIEUX = 'audacieux';
    case DISCORDANT = 'discordant';

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 0.8 => self::EXCELLENT,
            $score >= 0.6 => self::HARMONIEUX,
            $score >= 0.4 => self::AUDACIEUX,
            default => self::DISCORDANT,
        };
    }

    public function label(): string
    {
        return 'ui.pairing.'.$this->value;
    }
}
