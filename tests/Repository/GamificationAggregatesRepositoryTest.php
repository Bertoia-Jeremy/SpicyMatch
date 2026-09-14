<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Achievement;
use App\Entity\GameSession;
use App\Entity\Spices;
use App\Entity\SpiceView;
use App\Entity\UserAchievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use App\Repository\SpiceViewRepository;
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

    private function persistSession(Users $user, int $score, bool $finished): void
    {
        $session = new GameSession();
        $session->setUser($user)
            ->setGameMode(GameMode::QCM)
            ->setDifficulty(GameDifficulty::EASY)
            ->setTotalQuestions(10)
            ->setScore($score);

        if ($finished) {
            $session->finish();
        }

        $this->em->persist($session);
    }
}
