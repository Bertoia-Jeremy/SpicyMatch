<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Base for Live Component tests that assert on server-side state mutations
 * (session, LiveProp values, flash messages) without a browser.
 *
 * Extends IntegrationTestCase — gets a booted kernel, persisted session, and
 * QueryCountTrait. Subclasses instantiate the LC directly (LC classes are
 * services — pull them from the container).
 *
 * For genuine end-to-end interactions (click → re-render → DOM), use the
 * Playwright specs in `tests/playwright/`.
 */
abstract class LiveComponentTestCase extends IntegrationTestCase
{
    /**
     * Seed arbitrary state in the session slot used by a given game token —
     * lets tests exercise a mid-game scenario without replaying the mount.
     *
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
