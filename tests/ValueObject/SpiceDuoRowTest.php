<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\Enum\CookingMoment;
use App\ValueObject\SpiceDuoRow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpiceDuoRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string, CookingMoment}>
     */
    public static function momentProvider(): iterable
    {
        yield 'valeur entière' => ['2', CookingMoment::SIMMER];
        yield 'nom majuscule' => ['FINISH', CookingMoment::FINISH];
        yield 'nom minuscule' => ['plating', CookingMoment::PLATING];
        yield 'zéro' => ['0', CookingMoment::PRE];
    }

    #[DataProvider('momentProvider')]
    public function testMomentIsParsedFromValueOrName(string $raw, CookingMoment $expected): void
    {
        self::assertSame($expected, SpiceDuoRow::fromArray($this->valid([
            'moment' => $raw,
        ]))->moment);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'moment hors plage' => ['moment', '5'];
        yield 'moment inconnu' => ['moment', 'lunch'];
        yield 'rang zéro' => ['rank', '0'];
        yield 'rang trop haut' => ['rank', '10'];
        yield 'rang non numérique' => ['rank', 'a'];
        yield 'titre vide' => ['title', '  '];
        yield 'slug épice vide' => ['spice_slug', ''];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidFieldIsRejected(string $field, string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SpiceDuoRow::fromArray($this->valid([
            $field => $value,
        ]));
    }

    public function testMissingColumnIsRejected(): void
    {
        $data = $this->valid();
        unset($data['science']);

        $this->expectException(\InvalidArgumentException::class);

        SpiceDuoRow::fromArray($data);
    }

    public function testPairKeyIdentifiesSpiceMethodAndMoment(): void
    {
        $a = SpiceDuoRow::fromArray($this->valid());
        $sameTarget = SpiceDuoRow::fromArray($this->valid([
            'title' => 'Autre',
            'rank' => '2',
        ]));
        $otherMoment = SpiceDuoRow::fromArray($this->valid([
            'moment' => 'FINISH',
        ]));

        self::assertSame($a->pairKey(), $sameTarget->pairKey());
        self::assertNotSame($a->pairKey(), $otherMoment->pairKey());
    }

    public function testValuesAreTrimmed(): void
    {
        $row = SpiceDuoRow::fromArray($this->valid([
            'title' => '  Fond doré ',
            'rank' => ' 2 ',
        ]));

        self::assertSame('Fond doré', $row->title);
        self::assertSame(2, $row->rank);
    }

    /**
     * @param array<string, string> $override
     * @return array<string, string>
     */
    private function valid(array $override = []): array
    {
        return $override + [
            'spice_slug' => 'cumin',
            'method_slug' => 'torrefaction-a-sec',
            'moment' => 'START',
            'rank' => '1',
            'title' => 'Fond doré',
            'effect' => 'effet',
            'science' => 'science',
            'example' => 'exemple',
        ];
    }
}
