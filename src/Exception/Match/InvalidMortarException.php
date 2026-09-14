<?php

declare(strict_types=1);

namespace App\Exception\Match;

final class InvalidMortarException extends \InvalidArgumentException
{
    public static function invalidCount(): self
    {
        return new self('"spices" doit contenir entre 1 et 10 IDs valides.');
    }
}
