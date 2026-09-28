<?php

declare(strict_types=1);

namespace App\Enum;

enum CookingMoment: int
{
    case PRE = 0;
    case START = 1;
    case SIMMER = 2;
    case FINISH = 3;
    case PLATING = 4;

    public function label(): string
    {
        return $this->key('label');
    }

    public function hint(): string
    {
        return $this->key('hint');
    }

    public function chefView(): string
    {
        return $this->key('chef');
    }

    public function icon(): string
    {
        return match ($this) {
            self::PRE => 'fa-hourglass-half',
            self::START => 'fa-fire',
            self::SIMMER => 'fa-temperature-half',
            self::FINISH => 'fa-wand-magic-sparkles',
            self::PLATING => 'fa-plate-wheat',
        };
    }

    private function key(string $part): string
    {
        return 'enum.cooking_moment.' . strtolower($this->name) . '.' . $part;
    }
}
