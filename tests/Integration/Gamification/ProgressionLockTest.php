<?php

declare(strict_types=1);

namespace App\Tests\Integration\Gamification;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Gamification\GamificationManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProgressionLockTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private GamificationManagerInterface $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->manager = self::getContainer()->get(GamificationManagerInterface::class);
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

    public function testLockForUpdateFlushesAndLocksAFreshProgression(): void
    {
        $user = $this->createUser();
        $progression = $this->manager->getOrCreateProgression($user);
        self::assertNull($progression->getId());

        $this->manager->lockForUpdate($progression);

        self::assertNotNull($progression->getId());
    }

    public function testLockForUpdateOnPersistedProgressionKeepsPendingChanges(): void
    {
        $user = $this->createUser();
        $progression = $this->manager->getOrCreateProgression($user);
        $this->em->flush();

        $progression->setDiscoveries(7);
        $this->manager->lockForUpdate($progression);

        self::assertSame(7, $progression->getDiscoveries());

        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->em->getRepository(UserProgression::class)
            ->find((int) $progression->getId());
        self::assertNotNull($reloaded);
        self::assertSame(7, $reloaded->getDiscoveries());
    }

    private function createUser(): Users
    {
        $user = new Users();
        $user->setUsername('test_lock_' . uniqid());
        $user->setMail($user->getUsername() . '@example.com');
        $user->setPassword('hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
