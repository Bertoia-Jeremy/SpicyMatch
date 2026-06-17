<?php

declare(strict_types=1);

namespace App\Tests\Service\Slug;

use App\Service\Slug\SlugGenerator;
use PHPUnit\Framework\TestCase;

final class SlugGeneratorTest extends TestCase
{
    private SlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlugGenerator();
    }

    public function testSlugifyLowercasesAndStripsAccents(): void
    {
        self::assertSame('cannelle', $this->generator->slugify('Cannelle'));
        self::assertSame('curcuma-co', $this->generator->slugify('Curcuma & Co'));
        self::assertSame('poivre-noir', $this->generator->slugify('Poivre Noir'));
        self::assertSame('piment-d-espelette', $this->generator->slugify("Piment d'Espelette"));
    }

    public function testSlugifyFallsBackOnEmptyResult(): void
    {
        self::assertSame('n', $this->generator->slugify('---'));
    }

    public function testUniqueReturnsBaseWhenFree(): void
    {
        $slug = $this->generator->unique('Cannelle', static fn (string $s): bool => false);

        self::assertSame('cannelle', $slug);
    }

    public function testUniqueAppendsIncrementingSuffixOnCollision(): void
    {
        $taken = ['cannelle', 'cannelle-2'];

        $slug = $this->generator->unique('Cannelle', static fn (string $s): bool => in_array($s, $taken, true));

        self::assertSame('cannelle-3', $slug);
    }
}
