<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Enum\GameDifficulty;
use App\Repository\SpicesRepository;
use App\Service\Education\QcmQuestionGenerator;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

final class QcmQuestionGeneratorTest extends TestCase
{
    private const int GENERATION_RUNS = 50;
    private const int SHUFFLE_RUNS = 100;

    /**
     * @param list<int> $scores
     */
    #[DataProvider('scorePoolProvider')]
    public function testNoDistractorEverReachesTheCorrectAnswerScore(array $scores, GameDifficulty $difficulty): void
    {
        $generator = $this->makeGenerator($scores);
        $scoreById = $this->scoreIndex($scores);

        for ($run = 0; $run < self::GENERATION_RUNS; ++$run) {
            $question = $generator->generate($difficulty);

            self::assertNotNull($question);

            /** @var list<array{id: int, name: string}> $options */
            $options = $question['options'];
            $correctScore = (int) $question['metadata']['correctScore'];
            $correctName = $question['correctAnswer'];

            self::assertCount(4, $options);
            self::assertCount(4, array_unique(array_column($options, 'id')));

            foreach ($options as $option) {
                if ($option['name'] === $correctName) {
                    continue;
                }

                self::assertLessThan($correctScore, $scoreById[$option['id']]);
            }
        }
    }

    /**
     * @return iterable<string, array{list<int>, GameDifficulty}>
     */
    public static function scorePoolProvider(): iterable
    {
        $pools = [
            'échelle nominale 0-100' => [65, 64, 63, 60, 55, 52, 47, 30, 10],
            'échelle dégradée 0-65' => [42, 42, 41, 39, 36, 34, 31, 20, 7],
            'plateau haut avec queue basse' => [50, 50, 50, 50, 12, 11, 10, 9, 8],
        ];

        foreach ($pools as $label => $scores) {
            foreach (GameDifficulty::cases() as $difficulty) {
                yield $label.', '.$difficulty->value => [$scores, $difficulty];
            }
        }
    }

    public function testFlatPoolYieldsNoQuestionBecauseTheMaximumIsNotUnique(): void
    {
        $generator = $this->makeGenerator([30, 30, 30, 30, 30, 30, 30, 30, 30]);

        self::assertNull($generator->generate(GameDifficulty::HARD));
    }

    public function testCorrectAnswerReachesEveryDisplayedPosition(): void
    {
        $generator = $this->makeGenerator([65, 64, 63, 60, 55, 52, 47, 30, 10]);
        $positions = [];

        for ($run = 0; $run < self::SHUFFLE_RUNS; ++$run) {
            $question = $generator->generate(GameDifficulty::MEDIUM);

            self::assertNotNull($question);

            foreach (array_column($question['options'], 'name') as $position => $name) {
                if ($name === $question['correctAnswer']) {
                    $positions[$position] = true;
                }
            }
        }

        self::assertSame([0, 1, 2, 3], $this->sortedKeys($positions));
    }

    public function testHardDifficultyStillVariesTheOptionSetAcrossDraws(): void
    {
        $generator = $this->makeGenerator([65, 64, 63, 60, 55, 52, 47, 30, 10]);
        $signatures = [];

        for ($run = 0; $run < self::GENERATION_RUNS; ++$run) {
            $question = $generator->generate(GameDifficulty::HARD);

            self::assertNotNull($question);

            $ids = array_column($question['options'], 'id');
            sort($ids);
            $signatures[implode(',', $ids)] = true;
        }

        self::assertGreaterThanOrEqual(2, count($signatures));
    }

    /**
     * @param array<int, bool> $positions
     *
     * @return list<int>
     */
    private function sortedKeys(array $positions): array
    {
        $keys = array_keys($positions);
        sort($keys);

        return $keys;
    }

    /**
     * @param list<int> $scores
     */
    private function makeGenerator(array $scores): QcmQuestionGenerator
    {
        $spicesRepository = $this->createStub(SpicesRepository::class);
        $spicesRepository->method('findAllSpices')
            ->willReturn($this->makeBaseSpices());

        $finder = $this->createStub(CompatibleSpiceFinder::class);
        $finder->method('findCompatible')
            ->willReturn($this->makeScoredPool($scores));

        return new QcmQuestionGenerator($spicesRepository, $finder, new IdentityTranslator());
    }

    /**
     * @param list<int> $scores
     *
     * @return array<int, int>
     */
    private function scoreIndex(array $scores, int $firstId = 11): array
    {
        $index = [];

        foreach ($scores as $offset => $score) {
            $index[$firstId + $offset] = $score;
        }

        return $index;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function makeBaseSpices(): array
    {
        $spices = [];
        for ($id = 1; $id <= 5; ++$id) {
            $spices[] = [
                'id' => $id,
                'name' => 'Base '.$id,
                'groupName' => 'Groupe '.$id,
            ];
        }

        return $spices;
    }

    /**
     * @param list<int> $scores
     *
     * @return list<array<string, mixed>>
     */
    private function makeScoredPool(array $scores, int $firstId = 11): array
    {
        $pool = [];
        foreach ($scores as $offset => $score) {
            $pool[] = [
                'id' => $firstId + $offset,
                'name' => 'Épice '.($firstId + $offset),
                'score' => $score,
                'file' => null,
                'agId' => null,
                'color' => null,
                'groupName' => 'Groupe '.(($offset % 3) + 1),
                'stId' => null,
                'typeName' => null,
            ];
        }

        return $pool;
    }
}
