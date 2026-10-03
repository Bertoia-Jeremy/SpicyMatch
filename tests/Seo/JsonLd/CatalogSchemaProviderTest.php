<?php

declare(strict_types=1);

namespace App\Tests\Seo\JsonLd;

use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Seo\JsonLd\BreadcrumbSchemaProvider;
use App\Seo\JsonLd\CatalogTrail;
use App\Seo\JsonLd\CompoundSchemaProvider;
use App\Seo\JsonLd\DefinedTermSchemaProvider;
use App\ValueObject\PageTrail;
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

    public function testBreadcrumbListsHomeIndexAndEntityWithAbsoluteUrls(): void
    {
        $spice = new Spices()
            ->setName('Cannelle')
            ->setSlug('cannelle');

        self::assertSame([
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'ui.common.home',
                    'item' => 'https://x/fr/home',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'ui.catalog.breadcrumb_spices',
                    'item' => 'https://x/fr/index_spices',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => 'Cannelle',
                    'item' => 'https://x/fr/view_spice/cannelle',
                ],
            ],
        ], new BreadcrumbSchemaProvider($this->trail())
            ->build($spice, 'fr'));
    }

    public function testVisibleCrumbsShareTheTrailWithRelativePaths(): void
    {
        $group = new AromaticGroups()
            ->setName('Terpènes')
            ->setSlug('terpenes');

        self::assertSame([
            [
                'name' => 'ui.common.home',
                'url' => '/en/home',
            ],
            [
                'name' => 'ui.catalog.groups_title',
                'url' => '/en/index_aromatic_groups',
            ],
            [
                'name' => 'Terpènes',
                'url' => '/en/view_aromatic_groups/terpenes',
            ],
        ], $this->trail()
            ->crumbs($group, 'en', UrlGeneratorInterface::ABSOLUTE_PATH));
    }

    public function testPageTrailLinksHomeThenThePageButIsNoDefinedTerm(): void
    {
        $page = new PageTrail('site_plan', 'ui.site_plan.title');
        $trail = $this->trail();

        self::assertFalse($this->termProvider(null)->supports($page));
        self::assertSame([
            [
                'name' => 'ui.common.home',
                'url' => '/es/home',
            ],
            [
                'name' => 'ui.site_plan.title',
                'url' => '/es/site_plan',
            ],
        ], $trail->crumbs($page, 'es', UrlGeneratorInterface::ABSOLUTE_PATH));
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
            ->willReturnCallback(static fn (string $route, array $params, int $referenceType): string => ($referenceType === UrlGeneratorInterface::ABSOLUTE_URL ? 'https://x/' : '/') . $params['_locale'] . '/' . $route . (isset($params['slug']) ? '/' . $params['slug'] : ''));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnArgument(0);

        return new CatalogTrail($router, $translator);
    }
}
