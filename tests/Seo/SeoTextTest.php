<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Seo\SeoText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeoTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, int, string}>
     */
    public static function summaryProvider(): iterable
    {
        yield 'null' => [null, 20, ''];
        yield 'short text kept' => ['Cannelle douce', 20, 'Cannelle douce'];
        yield 'tags and entities stripped' => ['<p>Poivre&nbsp;<b>noir</b></p>', 20, 'Poivre noir'];
        yield 'whitespace collapsed' => ["Clou\n\n  de   girofle", 20, 'Clou de girofle'];
        yield 'cut on word boundary' => ['Arôme chaud et légèrement sucré', 20, 'Arôme chaud et…'];
        yield 'long word hard cut' => ['Anticonstitutionnellement', 10, 'Anticonst…'];
    }

    #[DataProvider('summaryProvider')]
    public function testSummaryIsPlainAndBounded(?string $text, int $maxLength, string $expected): void
    {
        $summary = SeoText::summary($text, $maxLength);

        self::assertSame($expected, $summary);
        self::assertLessThanOrEqual($maxLength, mb_strlen($summary));
    }
}
