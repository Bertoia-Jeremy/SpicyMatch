<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use App\Entity\AromaticCompound;
use App\Seo\SeoText;

final readonly class CompoundSchemaProvider implements SchemaProviderInterface
{
    public const string PUBCHEM_URL = 'https://pubchem.ncbi.nlm.nih.gov/compound/';

    public function __construct(
        private CatalogTrail $trail,
    ) {
    }

    public function supports(?object $subject): bool
    {
        return $subject instanceof AromaticCompound;
    }

    public function build(?object $subject, string $locale): array
    {
        \assert($subject instanceof AromaticCompound);

        $schema = [
            '@type' => ['MolecularEntity', 'DefinedTerm'],
            'name' => $this->trail->name($subject, $locale),
            'url' => $this->trail->url($subject, $locale),
        ];

        $description = SeoText::summary($subject->getLocalizedDescription($locale));
        if ($description !== '') {
            $schema['description'] = $description;
        }

        if ($subject->getFormula() !== null && $subject->getFormula() !== '') {
            $schema['molecularFormula'] = $subject->getFormula();
        }

        if ($subject->getInchiKey() !== null && $subject->getInchiKey() !== '') {
            $schema['inChIKey'] = $subject->getInchiKey();
        }

        if ($subject->getCasNumber() !== null && $subject->getCasNumber() !== '') {
            $schema['identifier'] = [
                '@type' => 'PropertyValue',
                'propertyID' => 'CAS',
                'value' => $subject->getCasNumber(),
            ];
        }

        if ($subject->getPubchemCid() !== null) {
            $schema['sameAs'] = self::PUBCHEM_URL . $subject->getPubchemCid();
        }

        $schema['inDefinedTermSet'] = $this->trail->termSet($subject, $locale);

        return $schema;
    }
}
