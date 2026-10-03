<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PreparationMethods;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PreparationMethodViewTest extends WebTestCase
{
    public function testRendersKitAdviceAndLinkedSpicesOutsideAnyFrame(): void
    {
        $client = self::createClient();
        $method = self::getContainer()->get(EntityManagerInterface::class)->getRepository(PreparationMethods::class)->findOneBy([], [
            'id' => 'ASC',
        ]);
        self::assertInstanceOf(PreparationMethods::class, $method);

        $crawler = $client->request('GET', '/fr/methodes-preparation/' . $method->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSame(1, substr_count($crawler->filter('main')->text(), (string) $method->getDescription()));
        self::assertCount(\count($method->getLocalizedToolList('fr')), $crawler->filter('.prep-tool'));
        self::assertSelectorTextContains('.prep-advice blockquote', (string) $method->getAdvice());
        self::assertGreaterThan(0, $crawler->filter('.prep-spices a.spice-row-link[href^="/fr/epices/"]')->count());
        self::assertCount(0, $crawler->filter('turbo-frame'));
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $client = self::createClient();

        $client->request('GET', '/fr/methodes-preparation/inconnue');

        self::assertResponseStatusCodeSame(404);
    }
}
