<?php

declare(strict_types=1);

namespace App\Tests\Support;

abstract class LiveComponentTestCase extends IntegrationTestCase
{
    /**
     * @param array<string, mixed> $state
     */
    protected function seedGameState(string $gameToken, array $state): void
    {
        $this->session->set('game_' . $gameToken, $state);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readGameState(string $gameToken): array
    {
        $raw = $this->session->get('game_' . $gameToken, []);

        return \is_array($raw) ? $raw : [];
    }
}
