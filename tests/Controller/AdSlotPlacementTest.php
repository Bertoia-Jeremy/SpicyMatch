<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Twig\Extension\AdsExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AdSlotPlacementTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function zoneProvider(): iterable
    {
        yield 'spices index' => ['/fr/epices/', 1];
        yield 'spices index page 2' => ['/fr/epices/?page=2', 1];
        yield 'catalogue index' => ['/en/spices/aromatic-compounds/', 1];
        yield 'academy' => ['/fr/education/', 1];
        yield 'home' => ['/fr/', 2];
        yield 'premium' => ['/fr/premium', 0];
    }

    #[DataProvider('zoneProvider')]
    public function testAdSlotsOnlyRenderInAllowedZones(string $url, int $expected): void
    {
        $client = self::createClient();
        self::enableAds();

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertCount($expected, $crawler->filter('[data-ad-slot]'));
        self::assertCount(0, $crawler->filter('script[src*="ethicalads"], script[src*="carbonads"]'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function gridProvider(): iterable
    {
        yield 'spices' => ['/fr/epices/?page=2', '[data-tour="spice-grid"].ad-grid-4'];
        yield 'catalogue' => ['/en/spices/aromatic-compounds/', '.ad-grid.ad-grid-3'];
    }

    #[DataProvider('gridProvider')]
    public function testCatalogAdSlotClosesTheGrid(string $url, string $grid): void
    {
        $client = self::createClient();
        self::enableAds();

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(\sprintf('%s > .ad-grid-slot:last-child [data-ad-slot]', $grid)));
    }

    public function testHomeKeepsASingleSlotForSingleSlotProvider(): void
    {
        $client = self::createClient();
        self::enableAds('carbon');

        $crawler = $client->request('GET', '/fr/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-ad-slot]'));
    }

    public static function enableAds(string $provider = 'ethicalads'): void
    {
        self::getContainer()->set(AdsExtension::class, new AdsExtension(
            self::getContainer()->get(TokenStorageInterface::class),
            true,
            $provider,
            'test-publisher',
            'test',
        ));
    }
}
