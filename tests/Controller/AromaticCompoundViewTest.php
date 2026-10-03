<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AromaticCompound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AromaticCompoundViewTest extends WebTestCase
{
    public function testRendersIdentitySheetThresholdsAndSpicesByPlane(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $compound = $em->getRepository(AromaticCompound::class)
            ->findOneBy([], [
                'id' => 'ASC',
            ]);
        self::assertInstanceOf(AromaticCompound::class, $compound);

        $em->getConnection()
            ->beginTransaction();
        try {
            $compound->setPubchemCid(3314);
            $em->flush();
            $em->clear();

            $crawler = $client->request('GET', '/en/spices/aromatic-compounds/' . $compound->getLocalizedSlug('en'));
        } finally {
            $em->getConnection()
                ->rollBack();
        }

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $compound->getLocalizedName('en'));
        self::assertGreaterThan(0, $crawler->filter('.cmpd-tile-formula sub')->count());
        self::assertGreaterThan(0, $crawler->filter('.cmpd-spec .cmpd-spec-item')->count());
        $pubchem = $crawler->filter('a.cmpd-external');
        self::assertSame('noopener noreferrer', $pubchem->attr('rel'));
        self::assertSame('https://pubchem.ncbi.nlm.nih.gov/compound/3314', $pubchem->attr('href'));
        self::assertGreaterThan(0, $crawler->filter('.cmpd-odt-table tbody tr')->count());
        self::assertGreaterThan(0, $crawler->filter('.cmpd-plane .spice-rail a.fil-card[href^="/en/spices/"]')->count());
        self::assertCount(1, $crawler->filter('.cmpd-confidence'));
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/fr/epices/composes-aromatiques/inconnu');

        self::assertResponseStatusCodeSame(404);
    }
}
