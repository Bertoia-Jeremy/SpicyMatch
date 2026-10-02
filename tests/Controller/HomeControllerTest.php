<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use App\Repository\UserProgressionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function localeProvider(): iterable
    {
        yield 'fr' => ['fr'];
        yield 'en' => ['en'];
        yield 'es' => ['es'];
    }

    #[DataProvider('localeProvider')]
    public function testAnonymousHomeRendersPromiseDemoAndPillars(string $locale): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/' . $locale . '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
        self::assertCount(\count(HomeController::DEMO_SPICE_SLUGS), $crawler->filter('.home-demo-chip'));
        self::assertCount(1, $crawler->filter('#experimenter'));
        self::assertCount(1, $crawler->filter('#apprendre'));
        self::assertCount(1, $crawler->filter('#jouer'));
        self::assertStringNotContainsString('ui.', $crawler->filter('main')->text());

        $heroCta = $crawler->filter('[data-home-cta="lab"]');
        self::assertCount(1, $heroCta);
        self::assertStringNotContainsString('register', (string) $heroCta->attr('href'));
    }

    public function testPlaySectionIsHiddenWhenGamificationIsOff(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()
            ->beginTransaction();

        try {
            $progression = self::getContainer()->get(UserProgressionRepository::class)->findOneBy([]);
            self::assertNotNull($progression, 'Base de test non seedée.');
            $progression->disableGamification();
            $em->flush();
            $user = $progression->getUser();
            self::assertNotNull($user);
            $client->loginUser($user);

            $crawler = $client->request('GET', '/fr/');

            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('#jouer'));
            self::assertCount(1, $crawler->filter('#experimenter'));
        } finally {
            $em->getConnection()
                ->rollBack();
        }
    }
}
