<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Gamification\GamificationManagerInterface;
use App\Message\EasterEggFoundEvent;
use App\MessageHandler\EasterEggGamificationHandler;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
final class EasterEggGamificationHandlerTest extends TestCase
{
    private UsersRepository&MockObject $usersRepo;

    private GamificationManagerInterface&MockObject $manager;

    private EntityManagerInterface&MockObject $em;

    private ProcessedGamificationEventRepository&MockObject $processedEvents;

    private EasterEggGamificationHandler $handler;

    protected function setUp(): void
    {
        $this->usersRepo = $this->createMock(UsersRepository::class);
        $this->manager = $this->createMock(GamificationManagerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->processedEvents = $this->createMock(ProcessedGamificationEventRepository::class);
        $this->processedEvents->method('claim')
            ->willReturn(true);

        $this->handler = new EasterEggGamificationHandler(
            $this->usersRepo,
            $this->manager,
            $this->em,
            $this->processedEvents,
            new NullLogger(),
        );
    }

    public function testReturnsEarlyWhenUserNotFound(): void
    {
        $this->usersRepo->expects(self::once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->manager->expects(self::never())->method('process');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)(new EasterEggFoundEvent(999, 'grain_de_sel'));
    }

    public function testCreatesProgressionWhenNull(): void
    {
        $user = $this->createConfiguredMock(Users::class, [
            'getProgression' => null,
        ]);
        $this->usersRepo->method('find')
            ->willReturn($user);

        $user->expects(self::once())->method('setProgression');
        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserProgression::class));

        $this->manager->expects(self::once())->method('process');
        $this->em->expects(self::once())->method('flush');

        ($this->handler)(new EasterEggFoundEvent(1, 'grain_de_sel'));
    }

    public function testDelegatesWithCorrectContext(): void
    {
        $progression = new UserProgression();
        $user = $this->createConfiguredMock(Users::class, [
            'getProgression' => $progression,
        ]);
        $this->usersRepo->method('find')
            ->willReturn($user);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'easter_egg_found', [
                'easterEggSlug' => 'secret_egg',
                'xpAmount' => 100,
            ]);

        ($this->handler)(new EasterEggFoundEvent(1, 'secret_egg', 100));
    }

    public function testDefaultXpAmountIs75(): void
    {
        $progression = new UserProgression();
        $user = $this->createConfiguredMock(Users::class, [
            'getProgression' => $progression,
        ]);
        $this->usersRepo->method('find')
            ->willReturn($user);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'easter_egg_found', [
                'easterEggSlug' => 'grain_de_sel',
                'xpAmount' => 75,
            ]);

        ($this->handler)(new EasterEggFoundEvent(1, 'grain_de_sel'));
    }
}
