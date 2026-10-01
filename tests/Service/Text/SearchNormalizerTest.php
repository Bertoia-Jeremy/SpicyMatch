<?php

declare(strict_types=1);

namespace App\Tests\Service\Text;

use App\Service\Text\SearchNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function matchProvider(): iterable
    {
        yield 'uppercase needle' => ['Cumin', 'CUMIN', true];
        yield 'lowercase needle on capitalised name' => ['Poivre noir', 'poivre', true];
        yield 'missing accent in needle' => ['Échalote', 'echalote', true];
        yield 'accent in needle only' => ['Anis etoile', 'étoilé', true];
        yield 'substring in the middle' => ['Poivre noir', 'noir', true];
        yield 'surrounding and repeated spaces' => ['Poivre  noir', '  poivre noir ', true];
        yield 'typographic apostrophe in name' => ['Herbes d’Espelette', "d'espelette", true];
        yield 'ligature' => ['Œillet', 'oeillet', true];
        yield 'blank needle matches everything' => ['Cannelle', '   ', true];
        yield 'unrelated needle' => ['Cannelle', 'cumin', false];
    }

    #[DataProvider('matchProvider')]
    public function testMatches(string $haystack, string $needle, bool $expected): void
    {
        self::assertSame($expected, new SearchNormalizer()->matches($haystack, $needle));
    }
}
