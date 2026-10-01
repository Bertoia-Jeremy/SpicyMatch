<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UsersRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class PageTourTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function tourPageProvider(): iterable
    {
        yield 'spices' => ['/fr/epices/', 'spices', ['spice-search', 'spice-grid', 'cta-lab']];
        yield 'lab' => ['/fr/spicymatch/', 'lab', ['mode-toggle', 'lab-results', 'lab-workspace', 'lab-context', 'lab-compose']];
        yield 'academy' => ['/fr/education/', 'academy', ['game-grid']];
    }

    /**
     * @param list<string> $targets
     */
    #[DataProvider('tourPageProvider')]
    public function testAnonymousPageDeclaresItsTranslatedTour(string $url, string $key, array $targets): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        $tour = $crawler->filter(\sprintf('[x-data="pageTour"][data-tour-key="%s"]', $key));
        self::assertCount(1, $tour);
        $steps = $tour->filter('[data-tour-step]');
        self::assertSame($targets, $steps->each(static fn (Crawler $step): string => (string) $step->attr('data-tour-step')));
        foreach ($steps->each(static fn (Crawler $step): string => (string) $step->attr('data-tour-title')) as $title) {
            self::assertStringStartsNotWith('ui.onboarding', $title);
        }
    }

    public function testRemovedOnboardingStateEndpointIsGone(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('POST', '/api/onboarding/state', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: '{"state":"welcome"}');

        self::assertResponseStatusCodeSame(404);
    }
}
