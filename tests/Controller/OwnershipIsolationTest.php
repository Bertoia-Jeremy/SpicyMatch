<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Achievement;
use App\Entity\GameSession;
use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\UserAchievement;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\UsersRepository;
use App\Service\GamificationManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OwnershipIsolationTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string, array<string, string>, string|null}>
     */
    public static function foreignHistoryRequestProvider(): iterable
    {
        yield 'view' => ['GET', '/fr/spicymatch/history/view/%d', [], null];
        yield 'rename' => ['POST', '/fr/spicymatch/history/%d/rename', [
            'CONTENT_TYPE' => 'application/json',
        ], '{"title":"hijacked"}'];
        yield 'favorite' => ['POST', '/fr/spicymatch/history/%d/favorite', [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'irrelevant',
        ], '{"favorite":true}'];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('foreignHistoryRequestProvider')]
    public function testForeignHistoryIsForbidden(string $method, string $url, array $server, ?string $content): void
    {
        $client = self::createClient();
        [$me, $other] = $this->twoUsers();
        $em = $this->em();
        [$history, $match] = $this->openHistory($em, $other);
        $ids = [$history->getId(), $match->getId()];
        $client->loginUser($me);

        try {
            $client->request($method, \sprintf($url, $ids[0]), [], [], $server, $content);

            self::assertResponseStatusCodeSame(403);
            $em->clear();
            $reloaded = $em->find(SpicyMatchHistory::class, $ids[0]);
            self::assertNotNull($reloaded);
            self::assertNull($reloaded->getTitle());
            self::assertFalse($reloaded->isFavorite());
        } finally {
            $this->remove([[SpicyMatchHistory::class, $ids[0]], [SpicyMatch::class, $ids[1]]]);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function foreignGameSessionRequestProvider(): iterable
    {
        yield 'play' => ['GET', '/fr/education/play/%d'];
        yield 'answer' => ['POST', '/fr/education/answer/%d'];
        yield 'result' => ['GET', '/fr/education/result/%d'];
    }

    #[DataProvider('foreignGameSessionRequestProvider')]
    public function testForeignGameSessionIsNotFound(string $method, string $url): void
    {
        $client = self::createClient();
        [$me, $other] = $this->twoUsers();
        $em = $this->em();
        $session = new GameSession()
            ->setUser($other)
            ->setGameMode(GameMode::QCM)
            ->setDifficulty(GameDifficulty::EASY);
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $client->loginUser($me);

        try {
            $client->request($method, \sprintf($url, $id));

            self::assertResponseStatusCodeSame(404);
        } finally {
            $this->remove([[GameSession::class, $id]]);
        }
    }

    public function testForeignBadgeCannotBeEquipped(): void
    {
        $client = self::createClient();
        [$me, $other] = $this->twoUsers();
        $em = $this->em();
        $achievement = $em->getRepository(Achievement::class)->findOneBy([]);
        self::assertNotNull($achievement, 'Fixtures must provide at least one achievement');
        $otherProgression = self::getContainer()->get(GamificationManager::class)->getOrCreateProgression($other);
        $myProgression = self::getContainer()->get(GamificationManager::class)->getOrCreateProgression($me);
        $equippedBefore = $myProgression->getEquippedBadge()?->getId();
        $badge = new UserAchievement()
            ->setUserProgression($otherProgression)
            ->setAchievement($achievement);
        $em->persist($badge);
        $em->flush();
        $id = $badge->getId();
        $client->loginUser($me);

        try {
            $client->request('POST', '/fr/users/badge/equip/' . $id, [
                '_token' => 'irrelevant',
            ]);

            self::assertResponseStatusCodeSame(403);
            $em->clear();
            self::assertSame($equippedBefore, $this->reloadUser($me)->getProgression()?->getEquippedBadge()?->getId());
        } finally {
            $this->remove([[UserAchievement::class, $id]]);
        }
    }

    public function testForeignAccountCannotBeDeleted(): void
    {
        $client = self::createClient();
        [$me, $other] = $this->twoUsers();
        $client->loginUser($me);

        $client->request('POST', '/fr/users/' . $other->getId(), [
            '_token' => 'irrelevant',
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em()
            ->clear();
        self::assertNull($this->reloadUser($other)->getDeletedAt());
    }

    public function testExportContainsOwnHistoryOnly(): void
    {
        $client = self::createClient();
        [$me, $other] = $this->twoUsers();
        $em = $this->em();
        [$mine, $myMatch] = $this->openHistory($em, $me);
        [$foreign, $foreignMatch] = $this->openHistory($em, $other);
        $ids = [$mine->getId(), $myMatch->getId(), $foreign->getId(), $foreignMatch->getId()];
        $client->loginUser($me);

        try {
            $client->request('GET', '/fr/users/export');

            self::assertResponseIsSuccessful();
            $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $exportedIds = array_column($payload['matchHistory'], 'id');
            self::assertContains($ids[0], $exportedIds);
            self::assertNotContains($ids[2], $exportedIds);
        } finally {
            $this->remove([
                [SpicyMatchHistory::class, $ids[0]],
                [SpicyMatch::class, $ids[1]],
                [SpicyMatchHistory::class, $ids[2]],
                [SpicyMatch::class, $ids[3]],
            ]);
        }
    }

    /**
     * @return array{Users, Users}
     */
    private function twoUsers(): array
    {
        $users = self::getContainer()->get(UsersRepository::class)->findBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ], 2);
        self::assertCount(2, $users, 'Fixtures must provide at least two users');

        return [$users[0], $users[1]];
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function reloadUser(Users $user): Users
    {
        $reloaded = $this->em()
            ->find(Users::class, $user->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    /**
     * @return array{SpicyMatchHistory, SpicyMatch}
     */
    private function openHistory(EntityManagerInterface $em, Users $user): array
    {
        $spice = $em->getRepository(Spices::class)->findOneBy([]);
        self::assertNotNull($spice);
        $match = new SpicyMatch()
            ->setUser($user)
            ->addSpice($spice);
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match);
        $em->persist($match);
        $em->persist($history);
        $em->flush();

        return [$history, $match];
    }

    /**
     * @param list<array{class-string, int|null}> $entities
     */
    private function remove(array $entities): void
    {
        $em = $this->em();
        $em->clear();
        foreach ($entities as [$class, $id]) {
            $entity = $id === null ? null : $em->find($class, $id);
            if ($entity !== null) {
                $em->remove($entity);
                $em->flush();
            }
        }
    }
}
