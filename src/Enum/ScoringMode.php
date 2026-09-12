<?php

declare(strict_types=1);

namespace App\Enum;

enum ScoringMode: string
{
    case HYBRID = 'hybrid';
    case OAV = 'oav';
    case FLAVORGRAPH = 'flavorgraph';
    case PRESENCE = 'presence';

    public static function resolve(bool $oavMode, bool $hybridizerActive): self
    {
        return match (true) {
            $oavMode && $hybridizerActive => self::HYBRID,
            $oavMode => self::OAV,
            $hybridizerActive => self::FLAVORGRAPH,
            default => self::PRESENCE,
        };
    }

    public function label(): string
    {
        return 'ui.lab.scoring_mode.' . $this->value;
    }
}
