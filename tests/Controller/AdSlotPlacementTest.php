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
        yield 'catalogue index' => ['/en/spices/aromatic-compounds/', 1];
        yield 'academy' => ['/fr/education/', 1];
        yield 'home' => ['/fr/', 0];
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

    public static function enableAds(): void
    {
        self::getContainer()->set(AdsExtension::class, new AdsExtension(
            self::getContainer()->get(TokenStorageInterface::class),
            true,
            'ethicalads',
            'test-publisher',
            'test',
        ));
    }
}
