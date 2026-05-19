<?php

declare(strict_types=1);

namespace App\Twig\Components\Education;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared session helpers for all game LiveComponents.
 * All LCs must define `$gameToken` (LiveProp string) and `$requestStack` (RequestStack).
 *
 * @property string       $gameToken
 * @property RequestStack $requestStack
 */
trait GameSessionTrait
{
    private function sessionKey(): string
    {
        return 'game_' . $this->gameToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSecret(): array
    {
        return $this->requestStack->getSession()
            ->get($this->sessionKey(), []);
    }

    /**
     * @param array<string, mixed> $secret
     */
    private function writeSecret(array $secret): void
    {
        $this->requestStack->getSession()
            ->set($this->sessionKey(), $secret);
    }

    private function removeSecret(): void
    {
        $this->requestStack->getSession()
            ->remove($this->sessionKey());
    }
}
