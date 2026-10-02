<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavbarRenderingTest extends WebTestCase
{
    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if ($this->connection?->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->connection = null;
        parent::tearDown();
    }

    public function testAnonymousNavbarPromotesLabAndAcademyWithoutAccountPushNorGameLinksNorSession(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/fr/');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.site-nav a[href="/fr/spicymatch/"]')->count());
        self::assertCount(0, $crawler->filter('.site-nav a[href="/register"]'));
        self::assertCount(0, $crawler->filter('#nav-panel a[href="/register"], #nav-panel a[href="/login"]'));
        self::assertCount(7, $crawler->filter('#nav-panel .nav-items .nav-item'));
        self::assertSame(['/fr/education/'], $crawler->filter('#nav-panel a[href*="/education/"]')->each(static fn ($a): string => (string) $a->attr('href')));
        self::assertCount(0, $crawler->filter('a[href^="/logout"]'));
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            self::assertStringNotContainsString('SESSID', $cookie->getName());
        }
    }

    public function testConnectedPillShowsGradeAndLevelWhenGamificationIsOn(): void
    {
        $client = self::createClient();
        $this->login($client, true);

        $crawler = $client->request('GET', '/fr/');

        self::assertResponseIsSuccessful();
        $pill = $crawler->filter('.site-nav [data-tour="nav-profile"]');
        self::assertCount(1, $pill);
        self::assertCount(1, $pill->filter('.profile-pill-level'));
        self::assertSame('Commis', trim($pill->filter('.profile-pill-grade')->text()));
        self::assertCount(1, $crawler->filter('#nav-profile-card [role="progressbar"]'));
    }

    public function testConnectedPillHidesProgressionWhenGamificationIsOff(): void
    {
        $client = self::createClient();
        $this->login($client, false);

        $crawler = $client->request('GET', '/fr/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.site-nav [data-tour="nav-profile"]'));
        self::assertCount(0, $crawler->filter('.site-nav .profile-pill-level, .site-nav .profile-pill-grade, .site-nav [role="progressbar"]'));
        self::assertCount(0, $crawler->filter('#nav-panel a[href*="/education/"]'));
    }

    public function testLogoutLinksCarryCsrfTokenAndBareLogoutIsRejected(): void
    {
        $client = self::createClient();
        $this->login($client, true);

        $crawler = $client->request('GET', '/fr/');
        $hrefs = array_unique($crawler->filter('a[href^="/logout"]')->each(static fn ($a): string => (string) $a->attr('href')));

        self::assertNotEmpty($hrefs);
        foreach ($hrefs as $href) {
            self::assertStringContainsString('_csrf_token=', $href);
        }

        $client->request('GET', '/logout');

        self::assertResponseStatusCodeSame(403);
    }

    private function login(KernelBrowser $client, bool $gamification): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([
            'username' => 'alice',
        ]);
        self::assertNotNull($user);
        $progression = $user->getProgression();
        self::assertNotNull($progression);
        $gamification ? $progression->enableGamification() : $progression->disableGamification();
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($user);
    }
}
