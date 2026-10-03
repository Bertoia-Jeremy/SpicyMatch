<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Attribute\AsTwigFilter;
use Twig\Markup;

final readonly class ChemistryExtension
{
    #[AsTwigFilter(name: 'chem_formula')]
    public function formula(?string $formula): Markup
    {
        $escaped = htmlspecialchars(trim((string) $formula), \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');

        return new Markup((string) preg_replace('/(?<=[A-Za-z)\]])(\d+)/', '<sub>$1</sub>', $escaped), 'UTF-8');
    }
}
