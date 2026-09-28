<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CookingTips;
use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpicyMatchLabQueryCountTest extends WebTestCase
{
    public function testTranslatedLocaleAddsNoPerTipQueries(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UsersRepository::class)->findOneBy([]);
        self::assertNotNull($user);
        $client->loginUser($user);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $lastMessageId = (int) $em->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(id), 0) FROM messenger_messages');

        $match = new SpicyMatch()
            ->setUser($user);
        foreach ($this->spicesWithTips($em) as $spice) {
            $match->addSpice($spice);
        }
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match);
        $em->persist($match);
        $em->persist($history);
        $em->flush();
        $matchId = $match->getId();
        $historyId = $history->getId();

        try {
            $fr = $this->queryCount($client, '/fr/spicymatch/history/' . $historyId . '/finalize');
            $en = $this->queryCount($client, '/en/spicymatch/history/' . $historyId . '/finalize');

            self::assertLessThanOrEqual($fr + 2, $en, \sprintf('fr=%d en=%d', $fr, $en));
        } finally {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            foreach ($em->getRepository(SpicyMatchHistory::class)->findBy([
                'spicyMatch' => $matchId,
            ]) as $history) {
                $em->remove($history);
            }
            $em->flush();
            $leftover = $em->find(SpicyMatch::class, $matchId);
            if ($leftover !== null) {
                $em->remove($leftover);
            }
            $em->flush();
            $em->getConnection()
                ->executeStatement('DELETE FROM messenger_messages WHERE id > :id', [
                    'id' => $lastMessageId,
                ]);
        }
    }

    private function queryCount(KernelBrowser $client, string $url): int
    {
        $client->enableProfiler();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertNotFalse($profile);

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /**
     * @return list<Spices>
     */
    private function spicesWithTips(EntityManagerInterface $em): array
    {
        $spices = [];
        foreach ($em->getRepository(CookingTips::class)->findAll() as $tip) {
            $spice = $tip->getSpice();
            if ($spice !== null && ! \in_array($spice, $spices, true) && $spice->getPreparationTips()->count() > 0) {
                $spices[] = $spice;
            }
            if (\count($spices) === 3) {
                break;
            }
        }
        self::assertNotEmpty($spices);

        return $spices;
    }
}
