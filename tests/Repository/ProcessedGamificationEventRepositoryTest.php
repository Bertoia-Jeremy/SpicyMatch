<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Users;
use App\Repository\ProcessedGamificationEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProcessedGamificationEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private ProcessedGamificationEventRepository $repo;

    private Users $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = self::getContainer()->get(ProcessedGamificationEventRepository::class);

        $this->user = $this->em->getRepository(Users::class)->findOneBy([]) ?? $this->createUser();
    }

    public function testClaimSucceedsFirstTime(): void
    {
        $key = 'test:' . uniqid();
        self::assertTrue($this->repo->claim($this->user, 'test_event', $key));
    }

    public function testClaimFailsOnDuplicate(): void
    {
        $key = 'test:' . uniqid();
        self::assertTrue($this->repo->claim($this->user, 'test_event', $key));
        self::assertFalse($this->repo->claim($this->user, 'test_event', $key));
    }

    public function testClaimSucceedsForDifferentKeys(): void
    {
        $suffix = uniqid();
        self::assertTrue($this->repo->claim($this->user, 'test_event', 'key_a:' . $suffix));
        self::assertTrue($this->repo->claim($this->user, 'test_event', 'key_b:' . $suffix));
    }

    public function testXpSnapshotSeesProgressionAndClaimTogether(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $user = $this->insertUser(withXp: 300);
            $key = 'session:' . uniqid();

            self::assertSame([
                'xp' => 300,
                'processed' => false,
            ], $this->repo->findXpSnapshot($user, 'game_completed', $key));

            $this->repo->claim($user, 'game_completed', $key);

            self::assertSame([
                'xp' => 300,
                'processed' => true,
            ], $this->repo->findXpSnapshot($user, 'game_completed', $key));
        } finally {
            $connection->rollBack();
        }
    }

    public function testXpSnapshotDefaultsToZeroWithoutProgression(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $user = $this->insertUser(withXp: null);

            self::assertSame([
                'xp' => 0,
                'processed' => false,
            ], $this->repo->findXpSnapshot($user, 'game_completed', 'session:' . uniqid()));
        } finally {
            $connection->rollBack();
        }
    }

    private function insertUser(?int $withXp): Users
    {
        $connection = $this->em->getConnection();
        $now = new \DateTimeImmutable()
            ->format('Y-m-d H:i:s');
        $username = 'xp_snapshot_' . bin2hex(random_bytes(4));

        $connection->insert('users', [
            'username' => $username,
            'mail' => $username . '@example.test',
            'password' => 'hash',
            'roles' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int) $connection->lastInsertId();

        if ($withXp !== null) {
            $connection->insert('user_progression', [
                'user_id' => $userId,
                'xp' => $withXp,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->em->getReference(Users::class, $userId);
    }

    private function createUser(): Users
    {
        $user = new Users();
        $user->setUsername('test_idempotency_' . uniqid());
        $user->setMail($user->getUsername() . '@example.com');
        $user->setPassword('hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
