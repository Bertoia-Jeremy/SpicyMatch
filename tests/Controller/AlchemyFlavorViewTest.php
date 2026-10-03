<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AlchemyFlavors;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AlchemyFlavorViewTest extends WebTestCase
{
    public function testRendersChainFromTasteToMoleculesToCarrierSpices(): void
    {
        $client = self::createClient();
        $flavor = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(AlchemyFlavors::class)
            ->findOneBy([], [
                'id' => 'ASC',
            ]);
        self::assertInstanceOf(AlchemyFlavors::class, $flavor);

        $crawler = $client->request('GET', '/en/spices/aromatic-flavors/' . $flavor->getLocalizedSlug('en'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', (string) $flavor->getLocalizedName('en'));
        self::assertCount(3, $crawler->filter('.flav-chain > .flav-step'));
        self::assertCount(
            $flavor->getAromaticsCompounds()
                ->count(),
            $crawler->filter('.flav-molecule[href^="/en/spices/aromatic-compounds/"]'),
        );
        $rows = $crawler->filter('.flav-where a.spice-row-link[href^="/en/spices/"]');
        self::assertGreaterThan(0, $rows->count());
        self::assertMatchesRegularExpression('/Leading note|Background note/', $rows->first()->filter('.spice-row-meta')->text());
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/fr/epices/saveurs-aromatiques/inconnue');

        self::assertResponseStatusCodeSame(404);
    }
}
