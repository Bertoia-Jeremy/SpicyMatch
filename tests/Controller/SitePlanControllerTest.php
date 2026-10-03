<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HelpController;
use App\EventSubscriber\SitemapSubscriber;
use App\Repository\UsersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitePlanControllerTest extends WebTestCase
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

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr', '/fr/plan-du-site', 'Plan du site'];
        yield 'en' => ['en', '/en/site-map', 'Site map'];
        yield 'es' => ['es', '/es/mapa-del-sitio', 'Mapa del sitio'];
    }

    #[DataProvider('locales')]
    public function testLinksEverySitemapPageAndIsReachableFromTheFooter(string $locale, string $path, string $title): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', $title);
        $plan = $crawler->filter('.plan-body');
        foreach (array_keys(SitemapSubscriber::STATIC_ROUTES) as $route) {
            if ($route !== 'site_plan') {
                self::assertSame(1, $this->countLinks($plan, $route, [
                    '_locale' => $locale,
                ]), $route);
            }
        }
        foreach (array_keys(HelpController::KNOWN_TOPICS) as $topic) {
            self::assertSame(1, $this->countLinks($plan, 'help_topic', [
                '_locale' => $locale,
                'topic' => $topic,
            ]), $topic);
        }
        self::assertGreaterThan(0, $plan->filter('.plan-columns .plan-link')->count());
        self::assertSame(1, $this->countLinks($plan, 'app_login'));
        self::assertSame(0, $this->countLinks($plan, 'index_spicy_match_history', [
            '_locale' => $locale,
        ]));
        self::assertSame($title, $crawler->filter('.plan-hero [aria-current="page"]')->text());
        $document = json_decode($crawler->filter('head script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('BreadcrumbList', $document['@type']);
        self::assertSame($title, $document['itemListElement'][1]['name']);
        self::assertStringEndsWith($path, $document['itemListElement'][1]['item']);
        self::assertCount(1, $crawler->filter(sprintf('footer a[href="%s"]', $path)));
    }

    public function testConnectedUserSeesPrivateLinksInsteadOfLogin(): void
    {
        $client = self::createClient();
        $this->login($client, true);

        $crawler = $client->request('GET', '/fr/plan-du-site');

        self::assertResponseIsSuccessful();
        $plan = $crawler->filter('.plan-body');
        self::assertSame(1, $this->countLinks($plan, 'index_spicy_match_history'));
        self::assertSame(1, $this->countLinks($plan, 'education_index'));
        foreach (['dashboard', 'grimoire', 'lab'] as $tab) {
            self::assertSame(1, $this->countLinks($plan, 'profile_user', [
                'tab' => $tab,
            ]), $tab);
        }
        self::assertSame(0, $this->countLinks($plan, 'app_login'));
        self::assertSame(0, $this->countLinks($plan, 'index_register'));
    }

    public function testAcademyIsHiddenWhenGamificationIsOff(): void
    {
        $client = self::createClient();
        $this->login($client, false);

        $crawler = $client->request('GET', '/fr/plan-du-site');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->countLinks($crawler->filter('.plan-body'), 'education_index'));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function countLinks(Crawler $scope, string $route, array $parameters = []): int
    {
        $href = self::getContainer()->get(UrlGeneratorInterface::class)->generate($route, $parameters);

        return $scope->filter(sprintf('a[href="%s"]', $href))
            ->count();
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
