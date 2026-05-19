<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Base class for integration tests needing a booted kernel + DB.
 *
 * Provides:
 *   - `$this->em`       — EntityManager from the container
 *   - `$this->session`  — a MockArraySessionStorage-backed Session, pushed on the RequestStack
 *   - `createTestUser()` — persists a minimal Users entity with a unique username
 *   - QueryCountTrait    — assertions on SQL query count
 *
 * Subclasses should extend this instead of KernelTestCase directly — keeps the
 * setup identical and prevents drift between test files.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    use QueryCountTrait;

    protected EntityManagerInterface $em;
    protected Session $session;
    protected RequestStack $requestStack;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->session = new Session(new MockArraySessionStorage());
        $this->requestStack = static::getContainer()->get(RequestStack::class);
        $request = new Request();
        $request->setSession($this->session);
        $this->requestStack->push($request);
    }

    protected function tearDown(): void
    {
        // Pop request we pushed in setUp — avoids leaking state across tests.
        while ($this->requestStack->getCurrentRequest() !== null) {
            $this->requestStack->pop();
        }

        parent::tearDown();
    }

    /**
     * Persist a minimal Users entity. Unique username avoids collisions across tests.
     */
    protected function createTestUser(string $prefix = 'test'): Users
    {
        $user = new Users();
        $user->setUsername($prefix . '_' . bin2hex(random_bytes(4)));
        $user->setMail($user->getUsername() . '@example.test');
        $user->setPassword('hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
