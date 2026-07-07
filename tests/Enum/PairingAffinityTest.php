<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\PairingAffinity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PairingAffinity::class)]
final class PairingAffinityTest extends TestCase
{
    /**
     * @return iterable<string, array{float, PairingAffinity}>
     */
    public static function scoreProvider(): iterable
    {
        yield 'perfect' => [1.0, PairingAffinity::EXCELLENT];
        yield 'excellent boundary' => [0.8, PairingAffinity::EXCELLENT];
        yield 'just below excellent' => [0.79, PairingAffinity::HARMONIEUX];
        yield 'harmonieux boundary' => [0.6, PairingAffinity::HARMONIEUX];
        yield 'just below harmonieux' => [0.59, PairingAffinity::AUDACIEUX];
        yield 'audacieux boundary' => [0.4, PairingAffinity::AUDACIEUX];
        yield 'just below audacieux' => [0.39, PairingAffinity::DISCORDANT];
        yield 'zero' => [0.0, PairingAffinity::DISCORDANT];
    }

    #[DataProvider('scoreProvider')]
    public function testFromScoreClassifies(float $score, PairingAffinity $expected): void
    {
        self::assertSame($expected, PairingAffinity::fromScore($score));
    }

    public function testLabelIsTranslationKey(): void
    {
        self::assertSame('ui.pairing.excellent', PairingAffinity::EXCELLENT->label());
        self::assertSame('ui.pairing.discordant', PairingAffinity::DISCORDANT->label());
    }
}
