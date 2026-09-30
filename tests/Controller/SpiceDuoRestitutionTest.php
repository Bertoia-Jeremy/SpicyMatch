<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpiceDuoRestitutionTest extends WebTestCase
{
    public function testHistoryPageShowsChefWordForChosenPair(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $client->loginUser($user);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = (int) $em->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        [$prep, $cook] = $this->pair($em);
        $match = new SpicyMatch()
            ->setUser($user)
            ->addSpice($prep->getSpice());
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match)
            ->addPreparationTip($prep)
            ->addCookingTip($cook);
        $duo = $this->duo($prep, $cook);
        $em->persist($match);
        $em->persist($history);
        $em->persist($duo);
        $em->flush();
        $ids = [$history->getId(), $match->getId(), $duo->getId()];

        try {
            $crawler = $client->request('GET', '/fr/spicymatch/history/view/' . $ids[0]);

            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Duo test chef', $crawler->filter('.chef-word-title')->text());
            self::assertStringContainsString('Effet test chef', $crawler->filter('.chef-word-list')->text());
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    public function testHistoryPageWithoutDuoHasNoChefWord(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $client->loginUser($user);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = (int) $em->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        [$prep, $cook] = $this->pair($em);
        $match = new SpicyMatch()
            ->setUser($user)
            ->addSpice($prep->getSpice());
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match)
            ->addPreparationTip($prep)
            ->addCookingTip($cook);
        $em->persist($match);
        $em->persist($history);
        $em->flush();
        $ids = [$history->getId(), $match->getId(), null];

        try {
            $crawler = $client->request('GET', '/fr/spicymatch/history/view/' . $ids[0]);

            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('.chef-word'));
            self::assertGreaterThan(0, $crawler->filter('.recipe-chef-empty')->count());
        } finally {
            $this->cleanup($ids, $lastMessageId);
        }
    }

    public function testSpicePageShowsDuoUnderMarmite(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        [$prep, $cook] = $this->pair($em);
        $spice = $prep->getSpice();
        $slug = $spice->getSlug();
        self::assertNotNull($slug);
        $duo = $this->duo($prep, $cook);
        $em->persist($duo);
        $em->flush();
        $duoId = $duo->getId();

        try {
            $crawler = $client->request('GET', '/fr/epices/' . $slug);

            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Duo test chef', $crawler->filter('.marmite-duo')->text());
        } finally {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            $toRemove = $em->find(SpiceDuo::class, $duoId);
            if ($toRemove !== null) {
                $em->remove($toRemove);
            }
            $em->flush();
        }
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

    private function duo(PreparationTips $prep, CookingTips $cook): SpiceDuo
    {
        return new SpiceDuo()
            ->setPreparationTip($prep)
            ->setCookingTip($cook)
            ->setTitle('Duo test chef')
            ->setEffect('Effet test chef')
            ->setScience('science')
            ->setExample('exemple')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
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
