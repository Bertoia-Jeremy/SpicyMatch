<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PreparationMethods;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreparationMethodsToolListTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('tools')]
    public function testSplitsToolsIntoCapitalisedItems(string $tools, array $expected): void
    {
        $method = new PreparationMethods()
            ->setTools($tools);

        self::assertSame($expected, $method->getLocalizedToolList('fr'));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function tools(): iterable
    {
        yield 'comma list' => ['Casserole, passoire fine, fouet.', ['Casserole', 'Passoire fine', 'Fouet']];
        yield 'sentences' => [
            'Aucun outil requis. Pinces pour le retrait.',
            ['Aucun outil requis', 'Pinces pour le retrait'],
        ];
        yield 'semicolons' => ['mortier ; pilon', ['Mortier', 'Pilon']];
        yield 'decimal kept' => ['Bocal de 1.5 l, entonnoir', ['Bocal de 1.5 l', 'Entonnoir']];
        yield 'empty' => ['', []];
    }
}
