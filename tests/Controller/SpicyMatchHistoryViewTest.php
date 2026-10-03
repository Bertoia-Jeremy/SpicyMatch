<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpicyMatchHistoryViewTest extends WebTestCase
{
    private KernelBrowser $client;

    private int $lastMessageId;

    private int $historyId;

    private int $matchId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->lastMessageId = (int) $em->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        $match = new SpicyMatch()
            ->setUser($user);
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match);
        foreach ($this->pairs($em) as [$prep, $cook]) {
            $spice = $prep->getSpice();
            self::assertNotNull($spice);
            $match->addSpice($spice);
            $history->addPreparationTip($prep)
                ->addCookingTip($cook);
        }
        $em->persist($match);
        $em->persist($history);
        $em->flush();
        $this->historyId = (int) $history->getId();
        $this->matchId = (int) $match->getId();
    }

    protected function tearDown(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        foreach ([[SpicyMatchHistory::class, $this->historyId], [SpicyMatch::class, $this->matchId]] as [$class, $id]) {
            $entity = $em->find($class, $id);
            if ($entity !== null) {
                $em->remove($entity);
            }
            $em->flush();
        }
        $em->getConnection()
            ->executeStatement('DELETE FROM messenger_messages WHERE id > :id', [
                'id' => $this->lastMessageId,
            ]);
        parent::tearDown();
    }

    public function testTranslatedLocaleAddsNoPerSpiceQueries(): void
    {
        $fr = $this->queryCount('/fr/spicymatch/history/view/' . $this->historyId);
        $en = $this->queryCount('/en/spicymatch/history/view/' . $this->historyId);

        self::assertLessThanOrEqual($fr + 2, $en, \sprintf('fr=%d en=%d', $fr, $en));
    }

    public function testRecipeViewNeverRendersAnAdSlot(): void
    {
        AdSlotPlacementTest::enableAds();

        $crawler = $this->client->request('GET', '/fr/spicymatch/history/view/' . $this->historyId);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-ad-slot]'));
    }

    public function testRecipeTabsUseRovingTabindexAndSpiceCardsAreHeadings(): void
    {
        $crawler = $this->client->request('GET', '/fr/spicymatch/history/view/' . $this->historyId);

        self::assertResponseIsSuccessful();
        $tabs = $crawler->filter('.recipe-segment[role="tablist"][aria-label] [role="tab"]');
        self::assertSame(['0', '-1'], $tabs->each(static fn ($tab): string => (string) $tab->attr('tabindex')));
        foreach ($tabs->each(static fn ($tab): string => (string) $tab->attr('aria-controls')) as $panel) {
            self::assertCount(1, $crawler->filter(\sprintf('#%s[role="tabpanel"]', $panel)));
        }
        $cards = $crawler->filter('article.recipe-card');
        self::assertGreaterThan(0, $cards->count());
        self::assertCount($cards->count(), $crawler->filter('article.recipe-card > h2 > button.recipe-card-head'));
    }

    public function testFavoriteIsIdempotentAndDispatchesOnce(): void
    {
        $token = $this->token();

        $this->postJson('favorite', [
            'favorite' => true,
        ], $token);
        self::assertResponseIsSuccessful();
        $this->postJson('favorite', [
            'favorite' => true,
        ], $token);
        self::assertResponseIsSuccessful();

        self::assertSame([
            'favorite' => true,
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertTrue($this->reloadHistory()->isFavorite());
        self::assertSame(1, $this->dispatchedFavoriteEvents());
    }

    public function testFavoriteRejectsInvalidCsrfToken(): void
    {
        $this->postJson('favorite', [
            'favorite' => true,
        ], 'invalid');

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->reloadHistory()->isFavorite());
    }

    public function testFavoriteRejectsNonBooleanValue(): void
    {
        $this->postJson('favorite', [
            'favorite' => 'yes',
        ], $this->token());

        self::assertResponseStatusCodeSame(400);
        self::assertFalse($this->reloadHistory()->isFavorite());
    }

    public function testRenameTruncatesToMaxLength(): void
    {
        $this->postJson('rename', [
            'title' => str_repeat('é', SpicyMatchHistory::TITLE_MAX_LENGTH + 30),
            '_token' => $this->token(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            str_repeat('é', SpicyMatchHistory::TITLE_MAX_LENGTH),
            $this->reloadHistory()
                ->getTitle(),
        );
    }

    private function token(): string
    {
        $crawler = $this->client->request('GET', '/fr/spicymatch/history/view/' . $this->historyId);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('[data-token]')
            ->attr('data-token');
        self::assertNotEmpty($token);

        return (string) $token;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $action, array $payload, ?string $token = null): void
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
        ];
        if ($token !== null) {
            $headers['HTTP_X_CSRF_TOKEN'] = $token;
        }
        $this->client->request(
            'POST',
            '/fr/spicymatch/history/' . $this->historyId . '/' . $action,
            server: $headers,
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function reloadHistory(): SpicyMatchHistory
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $history = $em->find(SpicyMatchHistory::class, $this->historyId);
        self::assertNotNull($history);

        return $history;
    }

    private function dispatchedFavoriteEvents(): int
    {
        return (int) self::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE id > :id AND body LIKE '%FavoriteToggledEvent%'",
                [
                    'id' => $this->lastMessageId,
                ],
            );
    }

    private function queryCount(string $url): int
    {
        $this->client->enableProfiler();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $profile = $this->client->getProfile();
        self::assertNotFalse($profile);

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /**
     * @return list<array{PreparationTips, CookingTips}>
     */
    private function pairs(EntityManagerInterface $em): array
    {
        $pairs = [];
        $seen = [];
        foreach ($em->getRepository(PreparationTips::class)->findAll() as $prep) {
            $spice = $prep->getSpice();
            if ($spice === null || isset($seen[$spice->getId()])) {
                continue;
            }
            $cook = $em->getRepository(CookingTips::class)->findOneBy([
                'spice' => $spice,
            ]);
            if ($cook instanceof CookingTips) {
                $seen[$spice->getId()] = true;
                $pairs[] = [$prep, $cook];
            }
            if (\count($pairs) === 3) {
                break;
            }
        }
        self::assertNotEmpty($pairs);

        return $pairs;
    }
}
