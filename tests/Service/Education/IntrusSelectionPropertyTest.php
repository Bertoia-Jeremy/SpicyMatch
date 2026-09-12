<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\SpicyType;
use App\Enum\GameDifficulty;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\IdentityTranslator;

final class IntrusSelectionPropertyTest extends TestCase
{
    private const int MAX_POOL_SIZE = 30;

    private const int SEEDS_PER_SIZE = 3;

    private const int BASE_SPICE_COUNT = 6;

    private const int FIRST_COMPATIBLE_ID = 100;

    private const int FIRST_INTRUDER_ID = 1000;

    /**
     * @param non-empty-string $shape
     */
    #[DataProvider('selectionScenarioProvider')]
    public function testGeneratedQuestionSatisfiesSelectionInvariants(
        string $shape,
        GameDifficulty $difficulty,
        bool $inverted,
    ): void {
        for ($size = 0; $size <= self::MAX_POOL_SIZE; ++$size) {
            for ($run = 0; $run < self::SEEDS_PER_SIZE; ++$run) {
                $seed = crc32($shape . '|' . $difficulty->value . '|' . $size . '|' . $run);

                foreach (self::ORDINAL_SCALES as $scaleName => $scale) {
                    mt_srand($seed);

                    $pool = $this->makePool($shape, $size, $seed, $scale);
                    $question = $this->generate($pool, $difficulty, $inverted);
                    $context = sprintf(
                        'shape=%s difficulty=%s inverted=%s size=%d seed=%d scale=%s',
                        $shape,
                        $difficulty->value,
                        $inverted ? 'true' : 'false',
                        $size,
                        $seed,
                        $scaleName,
                    );

                    $this->assertQuestionInvariants($pool, $question, $inverted, $context);
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string, GameDifficulty, bool}>
     */
    public static function selectionScenarioProvider(): iterable
    {
        $shapes = [
            'scores uniformes' => 'uniform',
            'tous scores égaux' => 'flat',
            'deux valeurs seulement' => 'two_values',
            'maximum inférieur à 3' => 'low_max',
            'intrus recoupant les compatibles' => 'overlapping',
        ];

        foreach ($shapes as $shapeLabel => $shape) {
            foreach (GameDifficulty::cases() as $difficulty) {
                foreach ([false, true] as $inverted) {
                    $label = sprintf(
                        '%s, %s, %s',
                        $shapeLabel,
                        $difficulty->value,
                        $inverted ? 'inversé' : 'classique',
                    );

                    yield $label => [$shape, $difficulty, $inverted];
                }
            }
        }
    }

    /**
     * @var array<string, array{float, int}>
     */
    private const array ORDINAL_SCALES = [
        'nominale' => [1.0, 0],
        'compressée' => [0.65, 0],
        'dilatée' => [2.0, 0],
        'décalée' => [1.0, 37],
    ];

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     * @param array<string, mixed>|null                                                                    $question
     */
    private function assertQuestionInvariants(array $pool, ?array $question, bool $inverted, string $context): void
    {
        self::assertSame(
            $this->isFeasible($pool, $inverted),
            $question !== null,
            'P6 ' . $context,
        );

        if ($question === null) {
            return;
        }

        self::assertSame('intrus', $question['type'], 'P0 ' . $context);

        /** @var list<array<string, mixed>> $options */
        $options = $question['options'];

        self::assertCount(4, $options, 'P1 ' . $context);

        $ids = array_map(static fn (array $option) => $option['id'], $options);
        self::assertCount(4, array_unique($ids), 'P2 ' . $context);

        $keySets = array_map(
            static function (array $option): string {
                $keys = array_keys($option);
                sort($keys);

                return implode(',', $keys);
            },
            $options,
        );
        self::assertCount(1, array_unique($keySets), 'P4 ' . $context);

        $correctId = $question['correctAnswerId'];
        self::assertContains($correctId, $ids, 'P5 ' . $context);

        $scores = $this->scoreIndex($pool);
        $correctScore = $scores[$correctId];

        foreach ($options as $option) {
            if ($option['id'] === $correctId) {
                continue;
            }

            $otherScore = $scores[$option['id']];

            if ($inverted && $question['isInverted'] === true) {
                self::assertGreaterThan($otherScore, $correctScore, 'P3 ' . $context);

                continue;
            }

            self::assertLessThan($otherScore, $correctScore, 'P3 ' . $context);
        }
    }

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     *
     * @return array<int, int>
     */
    private function scoreIndex(array $pool): array
    {
        $scores = [];

        foreach ($pool['intruders'] as $intruder) {
            $scores[(int) $intruder->getId()] = \PHP_INT_MIN;
        }

        foreach ($pool['compatibles'] as $compatible) {
            $scores[(int) $compatible['id']] = (int) $compatible['score'];
        }

        return $scores;
    }

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     */
    private function isFeasible(array $pool, bool $inverted): bool
    {
        $classic = $this->hasClassicSelection($pool);

        if (! $inverted) {
            return $classic;
        }

        return $this->hasInvertedSelection($pool) || $classic;
    }

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     */
    private function hasClassicSelection(array $pool): bool
    {
        $compatibles = $pool['compatibles'];

        if (count($compatibles) < 3) {
            return false;
        }

        if ($this->effectiveIntruders($pool) !== []) {
            return true;
        }

        foreach ($compatibles as $entry) {
            if ($this->countStrictlyAbove($compatibles, (int) $entry['score']) >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     */
    private function hasInvertedSelection(array $pool): bool
    {
        $compatibles = $pool['compatibles'];
        $intruderCount = count($this->effectiveIntruders($pool));

        foreach ($compatibles as $entry) {
            $below = $intruderCount + $this->countStrictlyBelow($compatibles, (int) $entry['score']);

            if ($below >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     *
     * @return list<Spices>
     */
    private function effectiveIntruders(array $pool): array
    {
        $compatibleIds = array_flip(array_map(static fn (array $c) => (int) $c['id'], $pool['compatibles']));

        return array_values(array_filter(
            $pool['intruders'],
            static fn (Spices $spice) => ! isset($compatibleIds[(int) $spice->getId()]),
        ));
    }

    /**
     * @param list<array<string, mixed>> $compatibles
     */
    private function countStrictlyAbove(array $compatibles, int $score): int
    {
        return count(array_filter($compatibles, static fn (array $c) => (int) $c['score'] > $score));
    }

    /**
     * @param list<array<string, mixed>> $compatibles
     */
    private function countStrictlyBelow(array $compatibles, int $score): int
    {
        return count(array_filter($compatibles, static fn (array $c) => (int) $c['score'] < $score));
    }

    /**
     * @param array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>} $pool
     *
     * @return array<string, mixed>|null
     */
    private function generate(array $pool, GameDifficulty $difficulty, bool $inverted): ?array
    {
        $spicesRepository = $this->createStub(SpicesRepository::class);
        $spicesRepository->method('findAllActive')
            ->willReturn($pool['bases']);
        $spicesRepository->method('findIncompatibleWith')
            ->willReturn($pool['intruders']);

        $finder = $this->createStub(CompatibleSpiceFinder::class);
        $finder->method('findCompatible')
            ->willReturn($pool['compatibles']);

        $manager = new AcademyManager(
            $spicesRepository,
            $finder,
            new ArrayAdapter(),
            new IdentityTranslator(),
        );

        return $manager->generateIntrusQuestion($difficulty, [], $inverted);
    }

    /**
     * @param array{float, int} $scale
     *
     * @return array{compatibles: list<array<string, mixed>>, intruders: list<Spices>, bases: list<Spices>}
     */
    private function makePool(string $shape, int $size, int $seed, array $scale): array
    {
        [$factor, $offset] = $scale;
        $rawScores = $this->makeScores($shape, $size, $seed);

        $compatibles = [];
        foreach ($rawScores as $index => $rawScore) {
            $compatibles[] = [
                'id' => self::FIRST_COMPATIBLE_ID + $index,
                'name' => 'Compatible ' . $index,
                'score' => (int) round($rawScore * $factor) + $offset,
                'file' => null,
                'agId' => 1 + ($index % 3),
                'color' => '#123456',
                'groupName' => 'Groupe ' . (1 + ($index % 3)),
                'stId' => 1 + ($index % 2),
                'typeName' => 'Type ' . (1 + ($index % 2)),
            ];
        }

        $intruderCount = $size % 3 === 0 ? 0 : 1 + ($seed % 3);
        $intruders = [];
        for ($index = 0; $index < $intruderCount; ++$index) {
            $id = $shape === 'overlapping' && $index === 0 && $size > 0
                ? self::FIRST_COMPATIBLE_ID
                : self::FIRST_INTRUDER_ID + $index;

            $intruders[] = $this->makeSpice($id, 'Intrus ' . $index, 1 + ($index % 2), 1 + ($index % 2));
        }

        $bases = [];
        for ($index = 0; $index < self::BASE_SPICE_COUNT; ++$index) {
            $bases[] = $this->makeSpice($index + 1, 'Base ' . ($index + 1), null, 1);
        }

        return [
            'compatibles' => $compatibles,
            'intruders' => $intruders,
            'bases' => $bases,
        ];
    }

    /**
     * @return list<int>
     */
    private function makeScores(string $shape, int $size, int $seed): array
    {
        $scores = [];

        for ($index = 0; $index < $size; ++$index) {
            $scores[] = match ($shape) {
                'flat' => 42,
                'two_values' => $index < intdiv($size, 2) ? 40 : 12,
                'low_max' => max(0, 3 - intdiv($index * 4, max(1, $size))),
                default => max(0, 65 - ($index * 2) - (($seed + $index) % 3)),
            };
        }

        rsort($scores);

        return $scores;
    }

    private function makeSpice(int $id, string $name, ?int $groupId, int $typeId): Spices
    {
        $type = new SpicyType();
        new \ReflectionProperty(SpicyType::class, 'id')->setValue($type, $typeId);
        $type->setName('Type ' . $typeId);

        $spice = new Spices();
        new \ReflectionProperty(Spices::class, 'id')->setValue($spice, $id);
        $spice->setName($name);
        $spice->setSpicyType($type);

        if ($groupId !== null) {
            $group = new AromaticGroups();
            new \ReflectionProperty(AromaticGroups::class, 'id')->setValue($group, $groupId);
            $group->setName('Groupe ' . $groupId);
            $group->setColor('#abcdef');

            $spice->setAromaticGroups($group);
        }

        return $spice;
    }
}
