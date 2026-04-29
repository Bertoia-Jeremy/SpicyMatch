<?php

declare(strict_types=1);

namespace App\Tests\Integration\Education;

use App\Service\Education\AcademyManager;
use App\Service\Education\DifficultyRuleApplier;
use App\Service\Education\GameSessionManager;
use App\Tests\Support\LiveComponentTestCase;
use App\Twig\Components\Education\IntrusGame;

/**
 * Security guard: replaying the same IntrusGame answer() payload must not
 * double-award points. The session tracks `answeredSteps[]` per question
 * nonce — the second attempt on the same step MUST be a silent no-op.
 */
final class IntrusGameReplayGuardTest extends LiveComponentTestCase
{
    public function testAnswerIsRejectedWhenStepAlreadyProcessed(): void
    {
        $container = static::getContainer();

        $game = new IntrusGame(
            $container->get(AcademyManager::class),
            $container->get(GameSessionManager::class),
            $container->get(DifficultyRuleApplier::class),
            $this->requestStack,
        );

        $game->gameToken = 'test_token';
        $game->questionNumber = 1;
        $this->seedGameState('test_token', [
            'correctAnswerId' => 42,
            'currentStep' => 1,
            'answeredSteps' => [],
            'correctSteps' => [],
        ]);
        $game->options = [
            [
                'id' => 42,
                'name' => 'X',
                'file' => null,
                'color' => null,
            ],
        ];

        // First answer — correct → should record one correctStep.
        $game->answer(42);

        $state = $this->readGameState('test_token');
        self::assertSame([1], $state['answeredSteps']);
        self::assertSame([1], $state['correctSteps']);

        // Second answer on the SAME step → must be a no-op (replay detected).
        $game->answer(42);

        $stateAfter = $this->readGameState('test_token');
        self::assertSame([1], $stateAfter['answeredSteps'], 'Replay must not append to answeredSteps');
        self::assertSame([1], $stateAfter['correctSteps'], 'Replay must not double-count correct answers');
    }
}
