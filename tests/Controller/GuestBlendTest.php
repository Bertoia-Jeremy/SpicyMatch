<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class GuestBlendTest extends WebTestCase
{
    private KernelBrowser $client;

    private int $lastMessageId;

    private int $historyId;

    private int $matchId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->lastMessageId = (int) $em->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        $match = new SpicyMatch();
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match);
        $cook = $em->getRepository(CookingTips::class)->findOneBy([]);
        self::assertNotNull($cook);
        $spice = $cook->getSpice();
        self::assertNotNull($spice);
        $prep = $em->getRepository(PreparationTips::class)->findOneBy([
            'spice' => $spice,
        ]);
        self::assertNotNull($prep);
        $match->addSpice($spice);
        $history->addCookingTip($cook)
            ->addPreparationTip($prep)
            ->markSealedIfComplete(new \DateTimeImmutable());
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
        $match = $em->find(SpicyMatch::class, $this->matchId);
        if ($match !== null) {
            $em->remove($match);
            $em->flush();
        }
        $em->getConnection()
            ->executeStatement('DELETE FROM messenger_messages WHERE id > :id', [
                'id' => $this->lastMessageId,
            ]);
        parent::tearDown();
    }

    public function testGuestFavoriteAsksForLoginThenIsClaimedWithTheBlend(): void
    {
        $this->ownInSession();
        $viewUrl = '/fr/spicymatch/history/view/' . $this->historyId;

        $this->client->request('GET', '/fr/spicymatch/history/' . $this->historyId . '/finalize');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', $viewUrl);
        self::assertResponseIsSuccessful();
        self::assertSame('1', $crawler->filter('[data-guest]')->attr('data-guest'));

        $this->client->request(
            'POST',
            '/fr/spicymatch/history/' . $this->historyId . '/favorite',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => (string) $crawler->filter('[data-token]')
                    ->attr('data-token'),
            ],
            content: json_encode([
                'favorite' => true,
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(401);
        self::assertFalse($this->reloadHistory()->isFavorite());

        $form = $this->client->request('GET', '/login')
            ->filter('#login-form')
            ->form();
        $form['username'] = 'alice';
        $form['password'] = 'Alice1234!';
        $form['_target_path'] = $viewUrl;
        $this->client->submit($form);
        self::assertResponseRedirects($viewUrl);

        $history = $this->reloadHistory();
        self::assertSame('alice', $history->getSpicyMatch()?->getUser()?->getUsername());
        self::assertTrue($history->isFavorite());
        self::assertSame(1, $this->messagesLike('MatchSavedEvent'));
        self::assertSame(1, $this->messagesLike('FavoriteToggledEvent'));
    }

    public function testGuestRenameAsksForLoginWithoutWriting(): void
    {
        $this->ownInSession();
        $crawler = $this->client->request('GET', '/fr/spicymatch/history/view/' . $this->historyId);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[contenteditable]'));

        $this->client->request(
            'POST',
            '/fr/spicymatch/history/' . $this->historyId . '/rename',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'title' => 'Mon mélange',
                '_token' => (string) $crawler->filter('[data-token]')
                    ->attr('data-token'),
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
        self::assertNull($this->reloadHistory()->getTitle());
    }

    public function testGuestBlendOfAnotherSessionIsNotReachable(): void
    {
        $this->client->request('GET', '/fr/spicymatch/history/view/' . $this->historyId);

        self::assertResponseRedirects('/login');
    }

    private function ownInSession(): void
    {
        $session = self::getContainer()->get('session.factory')->createSession();
        $session->set('guest_histories', [$this->historyId]);
        $session->save();
        $this->client->getCookieJar()
            ->set(new Cookie($session->getName(), $session->getId()));
    }

    private function reloadHistory(): SpicyMatchHistory
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $history = $em->find(SpicyMatchHistory::class, $this->historyId);
        self::assertNotNull($history);

        return $history;
    }

    private function messagesLike(string $class): int
    {
        return (int) self::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE id > :id AND body LIKE :class', [
                'id' => $this->lastMessageId,
                'class' => '%' . $class . '%',
            ]);
    }
}
