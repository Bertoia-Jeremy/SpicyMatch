<?php

declare(strict_types=1);

namespace App\Enum;

enum DataConfidence: string
{
    case MEASURED = 'measured';
    case LITERATURE = 'literature';
    case ESTIMATED = 'estimated';
    case PLACEHOLDER = 'placeholder';

    public function rank(): int
    {
        return match ($this) {
            self::MEASURED => 4,
            self::LITERATURE => 3,
            self::ESTIMATED => 2,
            self::PLACEHOLDER => 1,
        };
    }

    public function label(): string
    {
        return 'enum.confidence.' . $this->value;
    }

    public function tier(): string
    {
        return match ($this) {
            self::MEASURED => 'A',
            self::LITERATURE => 'B',
            self::ESTIMATED => 'C',
            self::PLACEHOLDER => 'D',
        };
    }

    public function isProductionGrade(): bool
    {
        return $this->rank() >= self::LITERATURE
            ->rank();
    }

    public static function weakest(self ...$confidences): self
    {
        if ($confidences === []) {
            return self::PLACEHOLDER;
        }

        $weakest = $confidences[0];
        foreach ($confidences as $c) {
            if ($c->rank() < $weakest->rank()) {
                $weakest = $c;
            }
        }

        return $weakest;
    }
}
