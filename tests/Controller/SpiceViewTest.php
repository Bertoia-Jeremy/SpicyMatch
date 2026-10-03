<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Spices;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        self::assertCount(1, $crawler->filter('h1'));
        self::assertCount(0, $crawler->filter('section aside, article aside'));
    }

    public function testEnglishPageLinksFacetsToGroupAndTypeAndShowsAdvantages(): void
    {
        $client = self::createClient();
        $spice = $this->firstSpice();
        $group = $spice->getAromaticGroups();
        $type = $spice->getSpicyType();
        self::assertNotNull($group);
        self::assertNotNull($type);
        $router = self::getContainer()->get(UrlGeneratorInterface::class);
        /** @var list<string> $expected */
        $expected = [
            $router->generate('view_aromatic_groups', [
                'slug' => $group->getLocalizedSlug('en'),
                '_locale' => 'en',
            ]),
            $router->generate('view_spicy_type', [
                'slug' => $type->getLocalizedSlug('en'),
                '_locale' => 'en',
            ]),
        ];
        $withAdvantages = $spice->getCookingTips()
            ->filter(static fn ($tip): bool => $tip->getDeletedAt() === null && trim((string) $tip->getLocalizedAdvantages('en')) !== '')
            ->count();

        $crawler = $client->request('GET', '/en/spices/' . $spice->getLocalizedSlug('en'));

        self::assertResponseIsSuccessful();
        $hrefs = $crawler->filter('.spc-facet')
            ->each(static fn (Crawler $a): string => (string) $a->attr('href'));
        self::assertSame($expected, $hrefs);
        self::assertStringContainsString((string) $type->getLocalizedName('en'), $crawler->filter('.spc-facets')->text());
        self::assertGreaterThan(0, $withAdvantages);
        self::assertGreaterThanOrEqual($withAdvantages, $crawler->filter('#spc-kitchen .spc-advantage')->count());
        self::assertCount(1, $crawler->filter('.spc-cta'));
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
