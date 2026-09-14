<?php

declare(strict_types=1);

namespace App\Enum;

enum AromaKinetics: string
{
    case HEAD = 'head';
    case HEART = 'heart';
    case BASE = 'base';

    public static function fromBoilingPoint(?int $celsius): ?self
    {
        if ($celsius === null) {
            return null;
        }

        return match (true) {
            $celsius < 150 => self::HEAD,
            $celsius > 250 => self::BASE,
            default => self::HEART,
        };
    }

    public function label(): string
    {
        return 'enum.kinetics.' . $this->value;
    }
}
