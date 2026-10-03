<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ResultTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResultTierTest extends TestCase
{
    #[DataProvider('accuracyProvider')]
    public function testFromAccuracyHonoursThresholds(float $accuracy, ResultTier $expected): void
    {
        self::assertSame($expected, ResultTier::fromAccuracy($accuracy));
    }

    /**
     * @return iterable<string, array{float, ResultTier}>
     */
    public static function accuracyProvider(): iterable
    {
        yield 'zero' => [0.0, ResultTier::FIRST_STEPS];
        yield 'just below awakening' => [39.9, ResultTier::FIRST_STEPS];
        yield 'awakening' => [40.0, ResultTier::AWAKENING];
        yield 'just below keen nose' => [69.9, ResultTier::AWAKENING];
        yield 'keen nose' => [70.0, ResultTier::KEEN_NOSE];
        yield 'just below master' => [89.9, ResultTier::KEEN_NOSE];
        yield 'master' => [90.0, ResultTier::MASTER];
        yield 'perfect' => [100.0, ResultTier::MASTER];
    }

    #[DataProvider('chainProvider')]
    public function testFromChainLengthHonoursThresholds(int $chain, ResultTier $expected): void
    {
        self::assertSame($expected, ResultTier::fromChainLength($chain));
    }

    /**
     * @return iterable<string, array{int, ResultTier}>
     */
    public static function chainProvider(): iterable
    {
        yield 'empty chain' => [0, ResultTier::FIRST_STEPS];
        yield 'two links' => [2, ResultTier::FIRST_STEPS];
        yield 'three links' => [3, ResultTier::AWAKENING];
        yield 'five links' => [5, ResultTier::AWAKENING];
        yield 'six links' => [6, ResultTier::KEEN_NOSE];
        yield 'nine links' => [9, ResultTier::KEEN_NOSE];
        yield 'ten links' => [10, ResultTier::MASTER];
    }

    public function testLabelsPointToResultTierKeys(): void
    {
        self::assertSame(
            ['ui.edu.result.tier.first_steps', 'ui.edu.result.tier.awakening', 'ui.edu.result.tier.keen_nose', 'ui.edu.result.tier.master'],
            array_map(static fn (ResultTier $tier): string => $tier->label(), ResultTier::cases()),
        );
    }
}
