<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ScoringMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScoringModeTest extends TestCase
{
    #[DataProvider('resolveProvider')]
    public function testResolve(bool $oavMode, bool $hybridizerActive, ScoringMode $expected): void
    {
        self::assertSame($expected, ScoringMode::resolve($oavMode, $hybridizerActive));
    }

    /**
     * @return iterable<string, array{bool, bool, ScoringMode}>
     */
    public static function resolveProvider(): iterable
    {
        yield 'oav data + hybridizer active' => [true, true, ScoringMode::HYBRID];
        yield 'oav data + hybridizer disabled' => [true, false, ScoringMode::OAV];
        yield 'no oav data + hybridizer active' => [false, true, ScoringMode::FLAVORGRAPH];
        yield 'no oav data + hybridizer disabled' => [false, false, ScoringMode::PRESENCE];
    }

    #[DataProvider('labelProvider')]
    public function testLabelIsTranslationKey(ScoringMode $mode, string $expected): void
    {
        self::assertSame($expected, $mode->label());
    }

    /**
     * @return iterable<string, array{ScoringMode, string}>
     */
    public static function labelProvider(): iterable
    {
        yield 'hybrid' => [ScoringMode::HYBRID, 'ui.lab.scoring_mode.hybrid'];
        yield 'oav' => [ScoringMode::OAV, 'ui.lab.scoring_mode.oav'];
        yield 'flavorgraph' => [ScoringMode::FLAVORGRAPH, 'ui.lab.scoring_mode.flavorgraph'];
        yield 'presence' => [ScoringMode::PRESENCE, 'ui.lab.scoring_mode.presence'];
    }
}
