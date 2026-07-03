<?php

declare(strict_types=1);

namespace App\Tests\Service\Gdpr;

use App\Command\GdprPurgeCommand;
use App\Entity\Users;
use App\Repository\ContactRepository;
use App\Repository\GdprRequestRepository;
use App\Repository\NewsletterSubscriptionRepository;
use App\Repository\UsersRepository;
use App\Service\Gdpr\UserAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class GdprPurgeCommandTest extends TestCase
{
    public function testExecutePurgesAndAnonymizes(): void
    {
        $user = new Users();
        new \ReflectionProperty(Users::class, 'id')->setValue($user, 5);
        $user->setUsername('a-purger');
        $user->setPassword('hash');

        $contactRepository = $this->createStub(ContactRepository::class);
        $contactRepository->method('purgeCreatedBefore')
            ->willReturn(3);

        $gdprRequestRepository = $this->createStub(GdprRequestRepository::class);
        $gdprRequestRepository->method('purgeCreatedBefore')
            ->willReturn(1);

        $usersRepository = $this->createStub(UsersRepository::class);
        $usersRepository->method('findAnonymizableDeletedBefore')
            ->willReturn([$user]);

        $newsletterRepository = $this->createStub(NewsletterSubscriptionRepository::class);
        $newsletterRepository->method('findBy')
            ->willReturn([]);
        $newsletterRepository->method('findByEmail')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('flush');

        $command = new GdprPurgeCommand(
            $contactRepository,
            $gdprRequestRepository,
            $usersRepository,
            new UserAnonymizer($entityManager, $newsletterRepository),
            $entityManager,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('3 contact(s)', $display);
        $this->assertStringContainsString('1 demande(s)', $display);
        $this->assertStringContainsString('1 compte(s) anonymisé(s)', $display);
        $this->assertSame('anonyme-5', $user->getUserIdentifier());
    }

    public function testExecuteWithoutUsersDoesNotFlush(): void
    {
        $contactRepository = $this->createStub(ContactRepository::class);
        $contactRepository->method('purgeCreatedBefore')
            ->willReturn(0);

        $gdprRequestRepository = $this->createStub(GdprRequestRepository::class);
        $gdprRequestRepository->method('purgeCreatedBefore')
            ->willReturn(0);

        $usersRepository = $this->createStub(UsersRepository::class);
        $usersRepository->method('findAnonymizableDeletedBefore')
            ->willReturn([]);

        $newsletterRepository = $this->createStub(NewsletterSubscriptionRepository::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())
            ->method('flush');

        $command = new GdprPurgeCommand(
            $contactRepository,
            $gdprRequestRepository,
            $usersRepository,
            new UserAnonymizer($entityManager, $newsletterRepository),
            $entityManager,
        );

        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute([]));
    }
}
