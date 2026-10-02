<?php

declare(strict_types=1);

namespace App\Enum;

enum ChefGrade: string
{
    case COMMIS = 'commis';
    case CHEF_PARTIE = 'chef_partie';
    case SAUCIER = 'saucier';
    case CHEF_EXECUTIF = 'chef_executif';

    public static function fromLevel(int $level): self
    {
        return match (true) {
            $level < 20 => self::COMMIS,
            $level < 50 => self::CHEF_PARTIE,
            $level < 80 => self::SAUCIER,
            default => self::CHEF_EXECUTIF,
        };
    }

    public function label(): string
    {
        return 'ui.grade.' . $this->value;
    }
}
