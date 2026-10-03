<?php

declare(strict_types=1);

namespace App\Tests\Seo\JsonLd;

use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Seo\JsonLd\CatalogTrail;
use App\Seo\JsonLd\CompoundSchemaProvider;
use App\Seo\JsonLd\DefinedTermSchemaProvider;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

final class CatalogSchemaProviderTest extends TestCase
{
    public function testCompoundExposesItsChemicalIdentity(): void
    {
        $compound = new AromaticCompound()
            ->setName('Carvacrol')
            ->setSlug('carvacrol')
            ->setDescription('Phénol aromatique.')
            ->setFormula('C10H14O')
            ->setInchiKey('RECUKUPTGUEGMW-UHFFFAOYSA-N')
            ->setCasNumber('499-75-2')
            ->setPubchemCid(10364);

        self::assertSame([
            '@type' => ['MolecularEntity', 'DefinedTerm'],
            'name' => 'Carvacrol',
            'url' => 'https://x/fr/view_aromatic_compound/carvacrol',
            'description' => 'Phénol aromatique.',
            'molecularFormula' => 'C10H14O',
            'inChIKey' => 'RECUKUPTGUEGMW-UHFFFAOYSA-N',
            'identifier' => [
                '@type' => 'PropertyValue',
                'propertyID' => 'CAS',
                'value' => '499-75-2',
            ],
            'sameAs' => 'https://pubchem.ncbi.nlm.nih.gov/compound/10364',
            'inDefinedTermSet' => [
                '@type' => 'DefinedTermSet',
                'name' => 'ui.catalog.compounds_title',
                'url' => 'https://x/fr/index_aromatic_compound',
            ],
        ], new CompoundSchemaProvider($this->trail())
            ->build($compound, 'fr'));
    }

    public function testCompoundWithoutIdentifiersOmitsThem(): void
    {
        $compound = new AromaticCompound()
            ->setName('Inconnu')
            ->setSlug('inconnu');

        $schema = new CompoundSchemaProvider($this->trail())
            ->build($compound, 'fr');

        self::assertSame(['@type', 'name', 'url', 'inDefinedTermSet'], array_keys($schema));
    }

    public function testCatalogEntityIsADefinedTermOfItsIndex(): void
    {
        $group = new AromaticGroups()
            ->setName('Terpènes')
            ->setSlug('terpenes')
            ->setDescription('Famille fraîche.');
        $provider = $this->termProvider(null);

        self::assertTrue($provider->supports($group));
        self::assertFalse($provider->supports(new AromaticCompound()));
        self::assertSame([
            '@type' => 'DefinedTerm',
            'name' => 'Terpènes',
            'url' => 'https://x/fr/view_aromatic_groups/terpenes',
            'description' => 'Famille fraîche.',
            'inDefinedTermSet' => [
                '@type' => 'DefinedTermSet',
                'name' => 'ui.catalog.groups_title',
                'url' => 'https://x/fr/index_aromatic_groups',
            ],
        ], $provider->build($group, 'fr'));
    }

    public function testSpiceTermCarriesItsHeroImage(): void
    {
        $spice = new Spices()
            ->setName('Cannelle')
            ->setSlug('cannelle');

        $schema = $this->termProvider('/uploads/cannelle.jpg')
            ->build($spice, 'fr');

        self::assertSame('https://x/media/spice_hero/uploads/cannelle.jpg', $schema['image']);
    }

    private function termProvider(?string $image): DefinedTermSchemaProvider
    {
        $storage = $this->createStub(StorageInterface::class);
        $storage->method('resolveUri')
            ->willReturn($image);
        $cache = $this->createStub(CacheManager::class);
        $cache->method('getBrowserPath')
            ->willReturnCallback(static fn (string $path, string $filter): string => 'https://x/media/' . $filter . $path);

        return new DefinedTermSchemaProvider($this->trail(), $storage, $cache);
    }

    private function trail(): CatalogTrail
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')
            ->willReturnCallback(static fn (string $route, array $params): string => 'https://x/' . $params['_locale'] . '/' . $route . (isset($params['slug']) ? '/' . $params['slug'] : ''));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnArgument(0);

        return new CatalogTrail($router, $translator);
    }
}
