<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Spices;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpiceViewTest extends WebTestCase
{
    public function testCompatibleRailLinksToOtherSpicesAndPrefillsTheLab(): void
    {
        $client = self::createClient();
        $spice = $this->firstSpice();

        $crawler = $client->request('GET', '/fr/epices/' . $spice->getSlug());

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('[data-rail="compatible"] a.fil-card')
            ->each(static fn ($a): string => (string) $a->attr('href'));
        self::assertNotEmpty($links);
        self::assertLessThanOrEqual(4, count($links));
        self::assertNotContains('/fr/epices/' . $spice->getSlug(), $links);
        self::assertCount(1, $crawler->filter(sprintf('[data-rail="compatible"] a[href="/fr/spicymatch/?spice=%s"]', $spice->getSlug())));
        self::assertCount(0, $crawler->filter('[x-data="contentRead"]'));
    }

    private function firstSpice(): Spices
    {
        $spice = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Spices::class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertInstanceOf(Spices::class, $spice);

        return $spice;
    }
}
