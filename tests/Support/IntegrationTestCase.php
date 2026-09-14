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
        while ($this->requestStack->getCurrentRequest() !== null) {
            $this->requestStack->pop();
        }

        parent::tearDown();
    }

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
