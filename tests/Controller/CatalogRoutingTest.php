<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AromaticGroups;
use App\Routing\CatalogPath;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class CatalogRoutingTest extends WebTestCase
{
    private const string EN_SLUG = 'routing-test-group-en';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function localizedIndexProvider(): iterable
    {
        yield 'groups fr' => ['index_aromatic_groups', 'fr', '/fr/epices/groupes-aromatiques/'];
        yield 'groups en' => ['index_aromatic_groups', 'en', '/en/spices/aromatic-groups/'];
        yield 'groups es' => ['index_aromatic_groups', 'es', '/es/especias/grupos-aromaticos/'];
        yield 'methods en' => ['index_preparation_methods', 'en', '/en/preparation-methods/'];
        yield 'types es' => ['index_spicy_type', 'es', '/es/especias/tipos-especias/'];
    }

    #[DataProvider('localizedIndexProvider')]
    public function testCatalogIndexesAreServedUnderTranslatedSegments(string $route, string $locale, string $path): void
    {
        $client = self::createClient();

        self::assertSame($path, self::getContainer()->get(RouterInterface::class)->generate($route, [
            '_locale' => $locale,
        ]));
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    public function testNoCatalogSubsectionIsCapturedBySpiceView(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        foreach (CatalogPath::spiceSubsections() as $prefixes) {
            foreach ($prefixes as $prefix) {
                foreach ([$prefix . '/', $prefix] as $path) {
                    $route = $router->match($path)['_route'] ?? '';
                    self::assertIsString($route);
                    self::assertStringStartsNotWith('view_spice', $route, $path);
                    self::assertStringStartsNotWith('quick_view_spice', $route, $path);
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string, int, string|null}>
     */
    public static function groupUrlProvider(): iterable
    {
        yield 'canonical french slug' => ['/fr/epices/groupes-aromatiques/{fr}', 200, null];
        yield 'numeric id' => ['/fr/epices/groupes-aromatiques/{id}', 301, '/fr/epices/groupes-aromatiques/{fr}'];
        yield 'french slug under english' => ['/en/spices/aromatic-groups/{fr}', 301, '/en/spices/aromatic-groups/{en}'];
        yield 'unknown slug' => ['/fr/epices/groupes-aromatiques/no-such-group', 404, null];
    }

    #[DataProvider('groupUrlProvider')]
    public function testGroupViewResolvesToTheCanonicalSlug(string $url, int $status, ?string $location): void
    {
        $client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        $group = $this->firstGroup();
        $placeholders = [
            '{id}' => (string) $group->getId(),
            '{fr}' => (string) $group->getSlug(),
            '{en}' => self::EN_SLUG,
        ];
        $connection->beginTransaction();

        try {
            $this->insertEnglishTranslation($connection, $group);

            $client->request('GET', strtr($url, $placeholders));

            self::assertResponseStatusCodeSame($status);
            if ($location !== null) {
                self::assertResponseRedirects(strtr($location, $placeholders), 301);
            }
        } finally {
            $connection->rollBack();
        }
    }

    public function testGroupViewAdvertisesTranslatedAlternatesAndSwitcherTargets(): void
    {
        $client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        $group = $this->firstGroup();
        $connection->beginTransaction();

        try {
            $this->insertEnglishTranslation($connection, $group);

            $crawler = $client->request('GET', '/fr/epices/groupes-aromatiques/' . $group->getSlug());

            self::assertResponseIsSuccessful();
            self::assertStringEndsWith(
                '/en/spices/aromatic-groups/' . self::EN_SLUG,
                (string) $crawler->filter('link[rel="alternate"][hreflang="en"]')
                    ->attr('href'),
            );
            self::assertStringEndsWith(
                '/fr/epices/groupes-aromatiques/' . $group->getSlug(),
                (string) $crawler->filter('link[rel="canonical"]')
                    ->attr('href'),
            );
            self::assertStringContainsString(
                'target=/en/spices/aromatic-groups/' . self::EN_SLUG,
                urldecode((string) $crawler->filter('a[hreflang="en"][href^="/locale/en"]')->first()->attr('href')),
            );
        } finally {
            $connection->rollBack();
        }
    }

    public function testTranslatedCatalogIndexAddsNoPerItemQueries(): void
    {
        $client = self::createClient();

        $fr = $this->queryCount($client, '/fr/epices/composes-aromatiques/');
        $en = $this->queryCount($client, '/en/spices/aromatic-compounds/');

        self::assertLessThanOrEqual($fr + 2, $en, \sprintf('fr=%d en=%d', $fr, $en));
    }

    private function firstGroup(): AromaticGroups
    {
        $group = self::getContainer()->get(EntityManagerInterface::class)->getRepository(AromaticGroups::class)->findOneBy([], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($group);

        return $group;
    }

    private function insertEnglishTranslation(Connection $connection, AromaticGroups $group): void
    {
        $connection->delete('aromatic_groups_translation', [
            'aromatic_groups_id' => $group->getId(),
            'locale' => 'en',
        ]);
        $connection->insert('aromatic_groups_translation', [
            'aromatic_groups_id' => $group->getId(),
            'locale' => 'en',
            'name' => 'Routing test group',
            'slug' => self::EN_SLUG,
            'reviewed' => 0,
        ]);
    }

    private function queryCount(KernelBrowser $client, string $url): int
    {
        $client->enableProfiler();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
