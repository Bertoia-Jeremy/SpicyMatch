<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\RecordState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecordStateTest extends TestCase
{
    #[DataProvider('scoreProvider')]
    public function testResolveComparesWithPreviousBest(int $score, ?int $previousBest, ?RecordState $expected): void
    {
        self::assertSame($expected, RecordState::resolve($score, $previousBest));
    }

    /**
     * @return iterable<string, array{int, ?int, ?RecordState}>
     */
    public static function scoreProvider(): iterable
    {
        yield 'first scored game' => [12, null, RecordState::FIRST];
        yield 'first game without points' => [0, null, null];
        yield 'beaten record' => [30, 20, RecordState::NEW];
        yield 'tied record' => [20, 20, RecordState::STANDING];
        yield 'below record' => [10, 20, RecordState::STANDING];
    }

    public function testOnlyStandingRecordIsNotCelebrated(): void
    {
        self::assertTrue(RecordState::FIRST->isCelebrated());
        self::assertTrue(RecordState::NEW->isCelebrated());
        self::assertFalse(RecordState::STANDING->isCelebrated());
    }
}
