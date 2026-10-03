<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\Extension\ChemistryExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChemistryExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function formulas(): iterable
    {
        yield 'simple' => ['C10H14O', 'C<sub>10</sub>H<sub>14</sub>O'];
        yield 'trailing digit' => ['C8H15NOS2', 'C<sub>8</sub>H<sub>15</sub>NOS<sub>2</sub>'];
        yield 'group multiplier' => ['Ca(OH)2', 'Ca(OH)<sub>2</sub>'];
        yield 'leading coefficient untouched' => ['2H2O', '2H<sub>2</sub>O'];
        yield 'markup escaped' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'];
        yield 'null' => [null, ''];
    }

    #[DataProvider('formulas')]
    public function testFormula(?string $formula, string $expected): void
    {
        self::assertSame($expected, (string) new ChemistryExtension()->formula($formula));
    }
}
