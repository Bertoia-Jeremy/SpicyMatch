<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Entity\GameSession;
use App\Entity\Spices;
use App\Entity\Users;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Twig\Components\Education\SurvivalGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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

    private function stubFinishedSession(): void
    {
        $this->sessionManager->method('createFinishedSession')
            ->willReturn(new GameSession());
    }

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{SurvivalGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_'.self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new SurvivalGame($this->academyManager, $this->sessionManager, $requestStack, $this->spicesRepo);
        $game->setContainer($this->makeContainer());
        $game->gameToken = self::TOKEN;
        $game->isStarted = true;
        $game->currentSpiceId = 1;
        $game->difficulty = 'easy';

        return [$game, $session];
    }

    private function makeContainer(): Container
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')
            ->willReturn(new Users());

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')
            ->willReturn($token);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')
            ->willReturn('/education/result/1');

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);

        return $container;
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

    public function testPickReplayGuardRejectsCurrentSpiceMismatch(): void
    {
        $secret = $this->withSecret(currentSpiceId: 2, compatibleIds: [42]);
        [$game] = $this->makeGame($secret);
        $game->currentSpiceId = 1;

        $game->pick(42);

        self::assertFalse($game->isGameOver);
        self::assertSame(0, $game->chainLength);
    }

    public function testPickIncompatibleSpiceSetsGameOver(): void
    {
        $this->stubFinishedSession();
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [2, 3]);
        [$game] = $this->makeGame($secret);

        $game->pick(99);

        self::assertTrue($game->isGameOver);
        self::assertSame(0, $game->chainLength);
    }

    public function testPickIncompatibleSpiceSetsLastPickedNameFromOptions(): void
    {
        $this->stubFinishedSession();
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

    public function testPickCompatibleSpiceIncrementsChainLength(): void
    {
        $this->stubFinishedSession();
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
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        self::assertSame(1, $game->chainLength);
        self::assertFalse($game->isGameOver);
    }

    public function testPickCompatibleSpicePassesChainLengthToFinishedSession(): void
    {
        $capturedCorrect = null;
        $this->sessionManager->method('createFinishedSession')
            ->willReturnCallback(function (...$args) use (&$capturedCorrect): GameSession {
                $capturedCorrect = $args[3];

                return new GameSession();
            });

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
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        self::assertSame(1, $capturedCorrect);
    }

    public function testChainLengthSurvivesMultiplePicksThroughGenerateOptions(): void
    {
        $capturedCorrect = null;
        $this->sessionManager->method('createFinishedSession')
            ->willReturnCallback(function (...$args) use (&$capturedCorrect): GameSession {
                $capturedCorrect = $args[3];

                return new GameSession();
            });

        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [10]);
        [$game] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 10,
                'name' => 'A',
                'file' => null,
                'color' => null,
                'groupName' => null,
            ],
        ];

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                10 => [
                    'id' => 10,
                    'name' => 'A',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#000',
                        'name' => 'G',
                    ],
                    'color' => null,
                ],
                20 => [
                    'id' => 20,
                    'name' => 'B',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#000',
                        'name' => 'G',
                    ],
                    'color' => null,
                ],
            ]);
        $this->spicesRepo->method('find')
            ->willReturn($this->createStub(Spices::class));
        $this->academyManager->method('generateSurvivalOptions')
            ->willReturn([
                [
                    'id' => 20,
                    'name' => 'B',
                    'file' => null,
                    'color' => null,
                    'groupName' => null,
                    'isCompatible' => true,
                ],
            ]);

        $game->pick(10);
        $game->pick(20);
        $game->pick(99);

        self::assertTrue($game->isGameOver);
        self::assertSame(2, $capturedCorrect);
    }

    public function testPickCompatibleSpiceTriggersVictoryWhenPoolExhausted(): void
    {
        $this->stubFinishedSession();
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
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        self::assertTrue($game->isVictory);
    }

    public function testPickLocalizesTheCurrentSpiceLikeTheOptions(): void
    {
        $this->stubFinishedSession();
        $secret = $this->withSecret(currentSpiceId: 1, compatibleIds: [42]);
        [$game] = $this->makeGame($secret);
        $game->options = [
            [
                'id' => 42,
                'name' => 'Pepper',
                'file' => null,
                'color' => null,
                'groupName' => 'Terpenes',
            ],
        ];

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                42 => [
                    'id' => 42,
                    'name' => 'Poivre',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#C00',
                        'name' => 'Terpènes',
                    ],
                ],
            ]);
        $this->academyManager->method('localizeSpiceSummaries')
            ->willReturnCallback(static fn (array $summaries): array => array_map(
                static fn (array $s): array => [
                    ...$s,
                    'name' => 'Pepper',
                    'groupName' => 'Terpenes',
                ],
                $summaries,
            ));
        $this->spicesRepo->method('find')
            ->willReturn(null);

        $game->pick(42);

        self::assertSame('Pepper', $game->currentSpiceName);
        self::assertSame('Terpenes', $game->currentSpiceGroupName);
    }

    public function testMountLocalizesTheStartingSpices(): void
    {
        [$game] = $this->makeGame();

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                42 => [
                    'id' => 42,
                    'name' => 'Poivre',
                    'file' => null,
                    'aromaticGroup' => [
                        'color' => '#C00',
                        'name' => 'Terpènes',
                    ],
                ],
            ]);
        $this->academyManager->method('localizeSpiceSummaries')
            ->willReturnCallback(static fn (array $summaries): array => array_map(
                static fn (array $s): array => [
                    ...$s,
                    'name' => 'Pepper',
                ],
                $summaries,
            ));

        $game->mount('easy');

        self::assertSame(['Pepper'], array_column($game->startingSpices, 'name'));
    }

    public function testPickCompatibleSpiceSetsGameOverWhenCardNotFound(): void
    {
        $this->stubFinishedSession();
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
            ->willReturn([]);

        $game->pick(42);

        self::assertTrue($game->isGameOver);
    }
}
