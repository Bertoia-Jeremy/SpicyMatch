<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpicyMatchHistoryFinalizeTest extends WebTestCase
{
    public function testFinalizeRendersDuoTooltipsAndMapWithoutWritingOnGet(): void
    {
        $client = self::createClient();
        $user = $this->loginFirstUser($client);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = $this->lastMessageId($em);

        [$prep, $cook] = $this->pair($em);
        $duo = new SpiceDuo()
            ->setPreparationTip($prep)
            ->setCookingTip($cook)
            ->setTitle('Duo test chef')
            ->setEffect('Effet test chef')
            ->setScience('science')
            ->setExample('exemple')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $em->persist($duo);
        [$history, $match] = $this->openHistory($em, $user, $prep);
        $ids = [$history->getId(), $match->getId(), $duo->getId()];

        try {
            $crawler = $client->request('GET', $this->finalizeUrl((int) $ids[0]));
            $client->request('GET', $this->finalizeUrl((int) $ids[0]));

            self::assertResponseIsSuccessful();
            self::assertStringContainsString('"byPrep"', (string) $crawler->filter('[data-duo-map]')->attr('data-duo-map'));
            self::assertGreaterThanOrEqual(2, $crawler->filter('[role="tooltip"]')->count());
            self::assertStringContainsString('Duo test chef', $crawler->filter('[role="tooltip"]')->first()->text());
            self::assertSame(1, $this->historyCount($em, (int) $ids[1]));
            self::assertSame($lastMessageId, $this->lastMessageId($em));
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    public function testFinalizeHydratesSavedSelections(): void
    {
        $client = self::createClient();
        $user = $this->loginFirstUser($client);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = $this->lastMessageId($em);

        [$prep, $cook] = $this->pair($em);
        [$history, $match] = $this->openHistory($em, $user, $prep);
        $history->addCookingTip($cook);
        $em->flush();
        $ids = [$history->getId(), $match->getId(), null];

        try {
            $crawler = $client->request('GET', $this->finalizeUrl((int) $ids[0]));

            $selections = json_decode((string) $crawler->filter('[data-selections]')->attr('data-selections'), true);
            self::assertSame([
                'cooking' => $cook->getId(),
                'preparation' => null,
            ], $selections[(string) $prep->getSpice()?->getId()]);
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    public function testEditSetsReplacesAndClearsIdempotently(): void
    {
        $client = self::createClient();
        $user = $this->loginFirstUser($client);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = $this->lastMessageId($em);

        [$prep, $cook] = $this->pair($em);
        [$history, $match] = $this->openHistory($em, $user, $prep);
        $ids = [$history->getId(), $match->getId(), null];
        $spiceId = (int) $prep->getSpice()?->getId();

        try {
            $crawler = $client->request('GET', $this->finalizeUrl((int) $ids[0]));
            $token = $this->editToken($crawler->filter('[x-data^="finalisationMelange"]')->attr('x-data') ?? '');

            $this->edit($client, (int) $ids[0], $token, $spiceId, 'cooking', (int) $cook->getId());
            $this->edit($client, (int) $ids[0], $token, $spiceId, 'cooking', (int) $cook->getId());
            self::assertResponseIsSuccessful();
            self::assertSame([(int) $cook->getId()], $this->tipIds($em, (int) $ids[0], 'cooking'));

            $this->edit($client, (int) $ids[0], $token, $spiceId, 'cooking', 0);
            self::assertResponseIsSuccessful();
            self::assertSame([], $this->tipIds($em, (int) $ids[0], 'cooking'));
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    /**
     * @param callable(int $spiceId, int $foreignTipId): array{int, string, int} $payload
     */
    #[DataProvider('invalidEdits')]
    public function testEditRejectsInvalidPayload(callable $payload, int $status): void
    {
        $client = self::createClient();
        $user = $this->loginFirstUser($client);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = $this->lastMessageId($em);

        [$prep] = $this->pair($em);
        $spice = $prep->getSpice();
        $foreign = null;
        foreach ($em->getRepository(CookingTips::class)->findAll() as $tip) {
            if ($tip->getSpice() !== $spice) {
                $foreign = $tip;
                break;
            }
        }
        self::assertNotNull($foreign);
        [$history, $match] = $this->openHistory($em, $user, $prep);
        $ids = [$history->getId(), $match->getId(), null];

        try {
            $crawler = $client->request('GET', $this->finalizeUrl((int) $ids[0]));
            $token = $this->editToken($crawler->filter('[x-data^="finalisationMelange"]')->attr('x-data') ?? '');

            [$spiceId, $kind, $tipId] = $payload((int) $spice?->getId(), (int) $foreign->getId());
            $this->edit($client, (int) $ids[0], $token, $spiceId, $kind, $tipId);

            self::assertResponseStatusCodeSame($status);
            self::assertSame([], $this->tipIds($em, (int) $ids[0], 'cooking'));
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    /**
     * @return iterable<string, array{callable(int, int): array{int, string, int}, int}>
     */
    public static function invalidEdits(): iterable
    {
        yield 'unknown kind' => [
            static fn (int $spiceId, int $foreignTipId): array => [$spiceId, 'garnish', $foreignTipId],
            400,
        ];
        yield 'spice outside the match' => [
            static fn (int $spiceId, int $foreignTipId): array => [0, 'cooking', $foreignTipId],
            400,
        ];
        yield 'tip of another spice' => [
            static fn (int $spiceId, int $foreignTipId): array => [$spiceId, 'cooking', $foreignTipId],
            404,
        ];
    }

    public function testHistoryViewRedirectsToFinalizationUntilSealed(): void
    {
        $client = self::createClient();
        $user = $this->loginFirstUser($client);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = $this->lastMessageId($em);

        [$prep] = $this->pair($em);
        [$history, $match] = $this->openHistory($em, $user, $prep);
        $history->addPreparationTip($prep);
        $em->flush();
        $ids = [$history->getId(), $match->getId(), null];

        try {
            $client->request('GET', '/fr/spicymatch/history/view/' . $ids[0]);

            self::assertResponseRedirects($this->finalizeUrl((int) $ids[0]));
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    public function testSealingDispatchesMatchSavedOnceAndCountsTheMatch(): void
    {
        $client = self::createClient();
        $user = $this->loginFirstUser($client);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = $this->lastMessageId($em);

        [$prep, $cook] = $this->pair($em);
        [$history, $match] = $this->openHistory($em, $user, $prep);
        $ids = [$history->getId(), $match->getId(), null];
        $spiceId = (int) $prep->getSpice()?->getId();
        $repository = self::getContainer()->get(SpicyMatchHistoryRepository::class);
        $countBefore = $repository->countByUser($user);

        try {
            $crawler = $client->request('GET', $this->finalizeUrl((int) $ids[0]));
            $token = $this->editToken($crawler->filter('[x-data^="finalisationMelange"]')->attr('x-data') ?? '');

            $this->edit($client, (int) $ids[0], $token, $spiceId, 'cooking', (int) $cook->getId());
            self::assertSame(0, $this->messagesSince($em, $lastMessageId));
            self::assertSame($countBefore, $repository->countByUser($user));

            $this->edit($client, (int) $ids[0], $token, $spiceId, 'preparation', (int) $prep->getId());
            self::assertResponseIsSuccessful();
            self::assertSame(1, $this->messagesSince($em, $lastMessageId));
            self::assertSame($countBefore + 1, $repository->countByUser($user));

            $this->edit($client, (int) $ids[0], $token, $spiceId, 'cooking', 0);
            $this->edit($client, (int) $ids[0], $token, $spiceId, 'cooking', (int) $cook->getId());
            self::assertSame(1, $this->messagesSince($em, $lastMessageId));
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    public function testForeignHistoryIsForbidden(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $users = self::getContainer()->get(UsersRepository::class)->findBy([], [
            'id' => 'ASC',
        ], 2);
        self::assertCount(2, $users, 'Fixtures must provide at least two users');
        $lastMessageId = $this->lastMessageId($em);

        [$prep, $cook] = $this->pair($em);
        [$history, $match] = $this->openHistory($em, $users[1], $prep);
        $ids = [$history->getId(), $match->getId(), null];
        $client->loginUser($users[0]);

        try {
            $client->request('GET', $this->finalizeUrl((int) $ids[0]));
            self::assertResponseStatusCodeSame(403);

            $this->edit($client, (int) $ids[0], 'irrelevant', (int) $prep->getSpice()?->getId(), 'cooking', (int) $cook->getId());
            self::assertResponseStatusCodeSame(403);
            self::assertSame([], $this->tipIds($em, (int) $ids[0], 'cooking'));
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    private function finalizeUrl(int $historyId): string
    {
        return '/fr/spicymatch/history/' . $historyId . '/finalize';
    }

    private function messagesSince(EntityManagerInterface $em, int $lastMessageId): int
    {
        return (int) $em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE id > :id', [
                'id' => $lastMessageId,
            ]);
    }

    private function loginFirstUser(KernelBrowser $client): Users
    {
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user, 'Fixtures must provide at least one user');
        $client->loginUser($user);

        return $user;
    }

    /**
     * @return array{PreparationTips, CookingTips}
     */
    private function pair(EntityManagerInterface $em): array
    {
        foreach ($em->getRepository(PreparationTips::class)->findAll() as $prep) {
            $spice = $prep->getSpice();
            $cook = $spice === null ? null : $em->getRepository(CookingTips::class)->findOneBy([
                'spice' => $spice,
            ]);
            if ($cook instanceof CookingTips) {
                return [$prep, $cook];
            }
        }

        self::fail('Fixtures must provide a spice with both tips');
    }

    /**
     * @return array{SpicyMatchHistory, SpicyMatch}
     */
    private function openHistory(EntityManagerInterface $em, Users $user, PreparationTips $prep): array
    {
        $spice = $prep->getSpice();
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

    private function editToken(string $xData): string
    {
        self::assertSame(1, preg_match("/'([^']+)'\\s*\\)\\s*$/", trim($xData), $m));

        return $m[1];
    }

    private function edit(KernelBrowser $client, int $historyId, string $token, int $spiceId, string $kind, int $tipId): void
    {
        $client->request('POST', '/fr/spicymatch/history/edit/' . $historyId, [
            'spiceId' => $spiceId,
            'kind' => $kind,
            'tipId' => $tipId,
        ], [], [
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
    }

    /**
     * @return list<int>
     */
    private function tipIds(EntityManagerInterface $em, int $historyId, string $kind): array
    {
        $table = $kind === 'cooking' ? 'spicy_match_history_cooking_tips' : 'spicy_match_history_preparation_tips';
        $column = $kind === 'cooking' ? 'cooking_tips_id' : 'preparation_tips_id';

        return array_map(intval(...), $em->getConnection()->fetchFirstColumn(
            sprintf('SELECT %s FROM %s WHERE spicy_match_history_id = :id', $column, $table),
            [
                'id' => $historyId,
            ],
        ));
    }

    private function historyCount(EntityManagerInterface $em, int $matchId): int
    {
        return (int) $em->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM spicy_match_history WHERE spicy_match_id = :id', [
                'id' => $matchId,
            ]);
    }

    private function lastMessageId(EntityManagerInterface $em): int
    {
        return (int) $em->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');
    }

    /**
     * @param array{int|null, int|null, int|null} $ids
     */
    private function cleanup(array $ids, int $lastMessageId): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        foreach ([[SpiceDuo::class, $ids[2]], [SpicyMatchHistory::class, $ids[0]], [SpicyMatch::class, $ids[1]]] as [$class, $id]) {
            $entity = $id === null ? null : $em->find($class, $id);
            if ($entity !== null) {
                $em->remove($entity);
            }
            $em->flush();
        }
        $em->getConnection()
            ->executeStatement('DELETE FROM messenger_messages WHERE id > :id', [
                'id' => $lastMessageId,
            ]);
    }
}
