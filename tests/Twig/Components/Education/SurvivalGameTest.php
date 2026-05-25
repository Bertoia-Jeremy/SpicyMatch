<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Twig\Components\Education\SurvivalGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Unit tests for SurvivalGame::pick() and SurvivalGame::start().
 *
 * Security focus: replay guard (session vs LiveProp currentSpiceId mismatch),
 * compatible/incompatible branching, victory detection.
 */
#[AllowMockObjectsWithoutExpectations]
final class SurvivalGameTest extends TestCase
{
    private const string TOKEN = 'survival_test_tok';

    private AcademyManager&MockObject $academyManager;
    private GameSessionManager&MockObject $sessionManager;
    private SpicesRepository&MockObject $spicesRepo;

    protected function setUp(): void
    {
        $this->academyManager = $this->createMock(AcademyManager::class);
        $this->sessionManager = $this->createMock(GameSessionManager::class);
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{SurvivalGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_' . self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new SurvivalGame($this->academyManager, $this->sessionManager, $requestStack, $this->spicesRepo);
        $game->gameToken = self::TOKEN;
        $game->isStarted = true;
        $game->currentSpiceId = 1;
        $game->difficulty = 'easy';

        return [$game, $session];
    }

    /**
     * @param int[] $compatibleIds
     *
     * @return array<string, mixed>
     */
    private function withSecret(int $currentSpiceId = 1, array $compatibleIds = []): array
    {
        return [
            'compatibleIds' => $compatibleIds,
            'currentSpiceId' => $currentSpiceId,
        ];
    }

    // ── pick() — guards ───────────────────────────────────────────────────────

    public function testPickDoesNothingWhenNotStarted(): void
    {
        [$game] = $this->makeGame($this->withSecret());
        $game->isStarted = false;

        $game->pick(42);

        self::assertFalse($game->isGameOver);
        self::assertSame(0, $game->chainLength);
    }

    public function testPickDoesNothingWhenGameOver(): void
    {
        [$game] = $this->makeGame($this->withSecret(compatibleIds: [42]));
        $game->isGameOver = true;

        $game->pick(42);

        self::assertSame(0, $game->chainLength);
    }

    public function testPickDoesNothingWhenVictory(): void
    {
        [$game] = $this->makeGame($this->withSecret(compatibleIds: [42]));
        $game->isVictory = true;

        $game->pick(42);

        self::assertSame(0, $game->chainLength);
    }

    /**
     * Security: client replays an old snapshot where currentSpiceId differs from session truth.
     */
    public function testPickReplayGuardRejectsCurrentSpiceMismatch(): void
    {
        // Session says currentSpiceId=2, LiveProp says currentSpiceId=1 → stale client
        $secret = $this->withSecret(currentSpiceId: 2, compatibleIds: [42]);
        [$game] = $this->makeGame($secret);
        $game->currentSpiceId = 1; // mismatch

        $game->pick(42);

        self::assertFalse($game->isGameOver);
        self::assertSame(0, $game->chainLength);
    }

    // ── pick() — incompatible spice ───────────────────────────────────────────

    public function testPickIncompatibleSpiceSetsGameOver(): void
    {
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [2, 3]);
        [$game] = $this->makeGame($secret);

        $game->pick(99); // not in compatibleIds

        self::assertTrue($game->isGameOver);
        self::assertSame(0, $game->chainLength);
    }

    public function testPickIncompatibleSpiceSetsLastPickedNameFromOptions(): void
    {
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: []);
        [$game] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 99,
                'name' => 'MauvaiseÉpice',
                'file' => null,
                'color' => null,
                'groupName' => null,
            ],
        ];

        $game->pick(99);

        self::assertSame('MauvaiseÉpice', $game->lastPickedName);
        self::assertTrue($game->isGameOver);
    }

    // ── pick() — compatible spice ─────────────────────────────────────────────

    public function testPickCompatibleSpiceIncrementsChainLength(): void
    {
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [42]);
        [$game] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 42,
                'name' => 'BonneÉpice',
                'file' => null,
                'color' => null,
                'groupName' => null,
            ],
        ];

        // findSpiceById(42) → getAllSpiceCards() with card 42
        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                42 => [
                    'id' => 42,
                    'name' => 'BonneÉpice',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#C00',
                        'name' => 'Chaud',
                    ],
                    'color' => null,
                ],
            ]);
        // generateOptions() → find(42) returns null → options = [] (triggers victory)
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        self::assertSame(1, $game->chainLength);
        self::assertFalse($game->isGameOver);
    }

    public function testPickCompatibleSpicePersistsChainLengthInSession(): void
    {
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [42]);
        [$game, $session] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 42,
                'name' => 'BonneÉpice',
                'file' => null,
                'color' => null,
                'groupName' => null,
            ],
        ];

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                42 => [
                    'id' => 42,
                    'name' => 'BonneÉpice',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#C00',
                        'name' => 'Chaud',
                    ],
                    'color' => null,
                ],
            ]);
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        $stored = $session->get('game_' . self::TOKEN);
        self::assertSame(1, $stored['chainLength']);
    }

    public function testPickCompatibleSpiceTriggersVictoryWhenPoolExhausted(): void
    {
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [42]);
        [$game] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 42,
                'name' => 'BonneÉpice',
                'file' => null,
                'color' => null,
                'groupName' => null,
            ],
        ];

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                42 => [
                    'id' => 42,
                    'name' => 'BonneÉpice',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#C00',
                        'name' => 'Chaud',
                    ],
                    'color' => null,
                ],
            ]);
        // find() → null → generateOptions() → options = [] → victory
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        self::assertTrue($game->isVictory);
    }

    public function testPickCompatibleSpiceSetsGameOverWhenCardNotFound(): void
    {
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [42]);
        [$game] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 42,
                'name' => 'BonneÉpice',
                'file' => null,
                'color' => null,
                'groupName' => null,
            ],
        ];

        // findSpiceById(42) → getAllSpiceCards() missing card 42 → null → isGameOver
        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([]);

        $game->pick(42);

        self::assertTrue($game->isGameOver);
    }
}
