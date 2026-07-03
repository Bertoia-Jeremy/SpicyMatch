<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Users;
use App\Repository\ProcessedGamificationEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Idempotency ledger — core of the handler anti-retry guard. If claim()
 * returns true twice for the same (eventType, eventKey), handlers will
 * double-award XP on Messenger retries.
 */
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

        // Use the first existing user — test only needs a persisted user for the FK.
        $this->user = $this->em->getRepository(Users::class)->findOneBy([]) ?? $this->createUser();
    }

    public function testClaimSucceedsFirstTime(): void
    {
        $key = 'test:'.uniqid();
        self::assertTrue($this->repo->claim($this->user, 'test_event', $key));
    }

    public function testClaimFailsOnDuplicate(): void
    {
        $key = 'test:'.uniqid();
        self::assertTrue($this->repo->claim($this->user, 'test_event', $key));
        self::assertFalse($this->repo->claim($this->user, 'test_event', $key));
    }

    public function testClaimSucceedsForDifferentKeys(): void
    {
        $suffix = uniqid();
        self::assertTrue($this->repo->claim($this->user, 'test_event', 'key_a:'.$suffix));
        self::assertTrue($this->repo->claim($this->user, 'test_event', 'key_b:'.$suffix));
    }

    private function createUser(): Users
    {
        $user = new Users();
        $user->setUsername('test_idempotency_'.uniqid());
        $user->setMail($user->getUsername().'@example.com');
        $user->setPassword('hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
