<?php

declare(strict_types=1);

namespace App\Exception\Match;

final class OavRebuildFailedException extends \RuntimeException
{
    public static function fromDbalException(\Throwable $cause): self
    {
        return new self('OAV table rebuild failed: ' . $cause->getMessage(), (int) $cause->getCode(), $cause);
    }
}
