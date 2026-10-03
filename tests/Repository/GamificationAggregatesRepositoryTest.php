<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Achievement;
use App\Entity\GameSession;
use App\Entity\PreparationMethods;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpiceView;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\UserAchievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GamificationAggregatesRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testSpiceViewAggregatesSeparateRowsFromDistinctSpices(): void
    {
        $user = $this->createUser();
        $spices = $this->em->getRepository(Spices::class)
            ->findBy([], [
                'id' => 'ASC',
            ], 2);
        self::assertCount(2, $spices);

        $this->persistView($user, $spices[0], 'today');
        $this->persistView($user, $spices[0], 'yesterday');
        $this->persistView($user, $spices[1], 'today');
        $this->em->flush();

        $repo = self::getContainer()->get(SpiceViewRepository::class);
        $userId = (int) $user->getId();

        self::assertSame(3, $repo->countGroupedByUser()[$userId] ?? null);
        self::assertSame(2, $repo->countDistinctSpicesGroupedByUser()[$userId] ?? null);
    }

    public function testMatchAggregatesCountOnlySealedHistories(): void
    {
        $user = $this->createUser();
        $spices = $this->em->getRepository(Spices::class)
            ->findBy([], [
                'id' => 'ASC',
            ], 3);
        self::assertCount(3, $spices);

        $this->persistHistory($user, [$spices[0], $spices[1]], sealed: true);
        $this->persistHistory($user, [$spices[2]], sealed: false);
        $this->em->flush();

        $repo = self::getContainer()->get(SpicyMatchHistoryRepository::class);
        $userId = (int) $user->getId();

        self::assertSame(1, $repo->countByUser($user));
        self::assertSame(2, $repo->countDistinctSpicesByUser($user));
        self::assertSame(1, $repo->countGroupedByUser()[$userId] ?? null);
        self::assertSame(2, $repo->countDistinctSpicesGroupedByUser()[$userId] ?? null);
    }

    public function testGameScoreSumIgnoresUnfinishedSessions(): void
    {
        $user = $this->createUser();

        $this->persistSession($user, 20, true);
        $this->persistSession($user, 30, true);
        $this->persistSession($user, 99, false);
        $this->em->flush();

        $repo = self::getContainer()->get(GameSessionRepository::class);

        self::assertSame(50, $repo->sumFinishedScoreGroupedByUser()[(int) $user->getId()] ?? null);
    }

    public function testBestScoreBeforeOnlyCountsEarlierFinishedSessionsOfSameMode(): void
    {
        $user = $this->createUser();

        $this->persistSession($user, 40, true, finishedAt: '-3 hours');
        $this->persistSession($user, 90, true, finishedAt: '-3 hours', mode: GameMode::CHRONO);
        $this->persistSession($user, 99, false);
        $first = $this->persistSession($user, 30, true, finishedAt: '-4 hours');
        $target = $this->persistSession($user, 55, true, finishedAt: '-2 hours');
        $this->persistSession($user, 70, true, finishedAt: '-1 hour');
        $this->em->flush();

        $repo = self::getContainer()->get(GameSessionRepository::class);

        self::assertSame(40, $repo->findBestScoreBefore($target));
        self::assertNull($repo->findBestScoreBefore($first));
    }

    public function testBadgeXpSumIsKeyedByProgression(): void
    {
        $user = $this->createUser();
        $progression = new UserProgression();
        $progression->setUser($user);
        $user->setProgression($progression);
        $this->em->persist($progression);

        $achievements = $this->em->getRepository(Achievement::class)
            ->findBy([], [
                'id' => 'ASC',
            ], 2);
        self::assertCount(2, $achievements);

        foreach ($achievements as $achievement) {
            $unlock = new UserAchievement();
            $unlock->setUserProgression($progression);
            $unlock->setAchievement($achievement);
            $this->em->persist($unlock);
        }

        $this->em->flush();

        $expected = $achievements[0]->getXpReward() + $achievements[1]->getXpReward();
        $repo = self::getContainer()->get(UserAchievementRepository::class);

        self::assertSame($expected, $repo->sumXpRewardGroupedByProgression()[(int) $progression->getId()] ?? null);
    }

    public function testCountsDistinctPreparationMethodsOfViewedSpicesOnly(): void
    {
        $user = $this->createUser();
        $spices = $this->em->getRepository(Spices::class)
            ->findBy([], [
                'id' => 'ASC',
            ], 2);
        self::assertCount(2, $spices);
        $methods = $this->em->getRepository(PreparationMethods::class)
            ->findBy([], [
                'id' => 'ASC',
            ], 2);
        self::assertCount(2, $methods);

        $this->persistTip($spices[0], $methods[0]);
        $this->persistTip($spices[0], $methods[1]);
        $this->persistTip($spices[0], $methods[0]);
        $this->persistView($user, $spices[0], 'today');
        $this->persistView($user, $spices[0], 'yesterday');
        $this->em->flush();
        $this->em->clear();

        $viewed = $this->em->find(Spices::class, $spices[0]->getId());
        self::assertInstanceOf(Spices::class, $viewed);
        $expected = [];
        foreach ($viewed->getPreparationTips() as $tip) {
            $expected[(int) $tip->getPreparationMethod()?->getId()] = true;
        }
        $user = $this->em->find(Users::class, $user->getId());
        self::assertInstanceOf(Users::class, $user);

        $count = self::getContainer()->get(SpiceViewRepository::class)->countDistinctPreparationMethodsSeenBy($user);

        self::assertGreaterThanOrEqual(2, $count);
        self::assertSame(\count($expected), $count);
    }

    private function persistTip(Spices $spice, PreparationMethods $method): void
    {
        $tip = new PreparationTips()
            ->setText('Texte')
            ->setAdvantages('Atout')
            ->setSpice($spice)
            ->setPreparationMethod($method)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($tip);
    }

    private function createUser(): Users
    {
        $user = new Users();
        $user->setUsername('test_aggregates_' . uniqid());
        $user->setMail($user->getUsername() . '@example.com');
        $user->setPassword('hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function persistView(Users $user, Spices $spice, string $day): void
    {
        $view = new SpiceView($user, $spice);
        new \ReflectionProperty(SpiceView::class, 'viewedDay')->setValue($view, new \DateTimeImmutable($day));
        $this->em->persist($view);
    }

    /**
     * @param list<Spices> $spices
     */
    private function persistHistory(Users $user, array $spices, bool $sealed): void
    {
        $match = new SpicyMatch()
            ->setUser($user);
        foreach ($spices as $spice) {
            $match->addSpice($spice);
        }
        $history = new SpicyMatchHistory()
            ->setSpicyMatch($match);
        if ($sealed) {
            new \ReflectionProperty(SpicyMatchHistory::class, 'sealedAt')->setValue($history, new \DateTimeImmutable());
        }
        $this->em->persist($match);
        $this->em->persist($history);
    }

    private function persistSession(
        Users $user,
        int $score,
        bool $finished,
        ?string $finishedAt = null,
        GameMode $mode = GameMode::QCM,
    ): GameSession {
        $session = new GameSession();
        $session->setUser($user)
            ->setGameMode($mode)
            ->setDifficulty(GameDifficulty::EASY)
            ->setTotalQuestions(10)
            ->setScore($score);

        if ($finished) {
            $session->finish();
        }

        if ($finishedAt !== null) {
            new \ReflectionProperty(GameSession::class, 'finishedAt')->setValue($session, new \DateTimeImmutable($finishedAt));
        }

        $this->em->persist($session);

        return $session;
    }
}
