<?php

declare(strict_types=1);

namespace App\Security;

final class RedirectTargetGuard
{
    public function isSafe(?string $target): bool
    {
        return null !== $target
            && '' !== $target
            && 1 !== preg_match('/[\x00-\x20\x7F]/', $target)
            && str_starts_with($target, '/')
            && ! str_starts_with($target, '//')
            && ! str_contains($target, '\\')
            && ! str_contains($target, '://');
    }

    public function safeOrNull(mixed $target): ?string
    {
        return \is_string($target) && $this->isSafe($target) ? $target : null;
    }
}
