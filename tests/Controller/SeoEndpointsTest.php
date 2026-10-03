<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\EventSubscriber\NoIndexSubscriber;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoEndpointsTest extends WebTestCase
{
    public function testSitemapListsEveryLocaleVariantWithAlternatesAndIsPubliclyCacheable(): void
    {
        $client = self::createClient();
        $spice = $this->firstSpice();

        $client->request('GET', '/sitemap.content.xml');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
        $xpath = new \DOMXPath($this->loadXml((string) $client->getResponse()->getContent()));
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('x', 'http://www.w3.org/1999/xhtml');

        foreach ([
            'fr' => '/fr/epices/',
            'en' => '/en/spices/',
            'es' => '/es/especias/',
        ] as $locale => $prefix) {
            $loc = $prefix . $spice->getLocalizedSlug($locale);
            $url = $xpath->query(\sprintf('//s:url[s:loc[substring(., string-length(.) - %d) = "%s"]]', \strlen($loc) - 1, $loc));
            self::assertNotFalse($url);
            self::assertSame(1, $url->length, $loc);
            $links = $xpath->query('x:link', $url->item(0));
            self::assertNotFalse($links);
            self::assertSame(4, $links->length, $loc);
        }
    }

    public function testRobotsAdvertisesTheAbsoluteSitemapAndHidesPrivateAreas(): void
    {
        $client = self::createClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()
            ->getContent();
        self::assertMatchesRegularExpression('#^Sitemap: https?://[^/\s]+/sitemap\.xml$#m', $body);
        self::assertStringContainsString("Disallow: /admin\n", $body);
        self::assertStringNotContainsString('VOTRE-DOMAINE', $body);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function noIndexProvider(): iterable
    {
        yield 'public catalogue' => ['/fr/epices/', false, false];
        yield 'profile' => ['/fr/users/profile', true, true];
        yield 'history' => ['/fr/spicymatch/history/', true, true];
    }

    #[DataProvider('noIndexProvider')]
    public function testPrivatePagesAreExcludedFromIndexing(string $url, bool $authenticated, bool $noIndex): void
    {
        $client = self::createClient();
        if ($authenticated) {
            $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
            self::assertNotNull($user);
            $client->loginUser($user);
        }

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        $header = $client->getResponse()
            ->headers->get(NoIndexSubscriber::HEADER);
        if ($noIndex) {
            self::assertSame(NoIndexSubscriber::DIRECTIVE, $header);
        } else {
            self::assertNotSame(NoIndexSubscriber::DIRECTIVE, $header);
        }
    }

    public function testSpiceViewEmbedsNoncedStructuredDataAndDescription(): void
    {
        $client = self::createClient();
        $spice = $this->firstSpice();

        $crawler = $client->request('GET', '/fr/epices/' . $spice->getSlug());

        self::assertResponseIsSuccessful();
        $script = $crawler->filter('head script[type="application/ld+json"]');
        self::assertCount(1, $script);
        self::assertNotEmpty($script->attr('nonce'));
        $document = json_decode($script->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame(['BreadcrumbList', 'DefinedTerm'], array_column($document['@graph'], '@type'));
        self::assertNotSame('', $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertSame('article', $crawler->filter('meta[property="og:type"]')->attr('content'));
        self::assertCount(1, $crawler->filter('main h1'));
        self::assertCount(1, $crawler->filter('main article .detail-lead dfn'));
    }

    public function testVisibleBreadcrumbMirrorsTheBreadcrumbList(): void
    {
        $client = self::createClient();
        $spice = $this->firstSpice();

        $crawler = $client->request('GET', '/en/spices/' . $spice->getLocalizedSlug('en'));

        self::assertResponseIsSuccessful();
        $document = json_decode($crawler->filter('head script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        $list = $document['@graph'][0]['itemListElement'];
        $crumbs = $crawler->filter('main nav.detail-crumbs li');

        self::assertSame(array_column($list, 'name'), $crumbs->each(static fn ($li): string => trim($li->filter('a, [aria-current] span')->text())));
        self::assertSame(
            array_map(static fn (string $url): string => (string) parse_url($url, \PHP_URL_PATH), array_slice(array_column($list, 'item'), 0, -1)),
            $crumbs->filter('a')
                ->each(static fn ($a): string => (string) $a->attr('href')),
        );
        self::assertSame('page', $crumbs->last()->attr('aria-current'));
        self::assertCount(2, $crawler->filter('main nav.detail-crumbs [aria-hidden="true"]'));
        self::assertStringStartsWith('http', $list[0]['item']);
        self::assertStringEndsWith('/en/spices/' . $spice->getLocalizedSlug('en'), $list[2]['item']);
    }

    public function testCompoundViewDescribesAMolecularEntity(): void
    {
        $client = self::createClient();
        $compound = self::getContainer()->get(EntityManagerInterface::class)->getRepository(AromaticCompound::class)->findOneBy([], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($compound);

        $crawler = $client->request('GET', '/en/spices/aromatic-compounds/' . $compound->getLocalizedSlug('en'));

        self::assertResponseIsSuccessful();
        $document = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame([['MolecularEntity', 'DefinedTerm']], array_slice(array_column($document['@graph'], '@type'), 1));
        self::assertStringEndsWith('/en/spices/aromatic-compounds/', $document['@graph'][1]['inDefinedTermSet']['url']);
    }

    private function firstSpice(): Spices
    {
        $spice = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Spices::class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($spice);

        return $spice;
    }

    private function loadXml(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));

        return $document;
    }
}
