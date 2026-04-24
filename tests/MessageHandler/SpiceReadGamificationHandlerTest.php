<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Gamification\GamificationManagerInterface;
use App\Message\SpiceReadEvent;
use App\MessageHandler\SpiceReadGamificationHandler;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
final class SpiceReadGamificationHandlerTest extends TestCase
{
    private UsersRepository&MockObject $usersRepo;
    private SpicesRepository&MockObject $spicesRepo;
    private SpiceViewRepository&MockObject $spiceViewRepo;
    private GamificationManagerInterface&MockObject $manager;
    private EntityManagerInterface&MockObject $em;
    private ProcessedGamificationEventRepository&MockObject $processedEvents;
    private SpiceReadGamificationHandler $handler;

    protected function setUp(): void
    {
        $this->usersRepo = $this->createMock(UsersRepository::class);
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
        $this->spiceViewRepo = $this->createMock(SpiceViewRepository::class);
        $this->manager = $this->createMock(GamificationManagerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->processedEvents = $this->createMock(ProcessedGamificationEventRepository::class);
        $this->processedEvents->method('claim')
            ->willReturn(true);

        $this->handler = new SpiceReadGamificationHandler(
            $this->usersRepo,
            $this->spicesRepo,
            $this->spiceViewRepo,
            $this->manager,
            $this->em,
            $this->processedEvents,
            new NullLogger(),
        );
    }

    public function testInvokeReturnsEarlyWhenUserNotFound(): void
    {
        $this->usersRepo->method('find')
            ->willReturn(null);
        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new SpiceReadEvent(999, 1, true));
    }

    public function testInvokeSetsDiscoveryCountFromDistinctQuery(): void
    {
        $stats = new UserStat();
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);
        $user->method('getStats')
            ->willReturn($stats);
        $progression->setUser($user);

        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->spiceViewRepo->method('countDistinctSpicesByUser')
            ->willReturn(12);
        $this->spicesRepo->method('find')
            ->willReturn(null);
        $this->manager->method('getOrCreateStats')
            ->willReturn($stats);

        ($this->handler)(new SpiceReadEvent(1, 42, true));

        self::assertSame(12, $progression->getDiscoveries());
    }

    public function testInvokeIncrementsSpicesReadOnNewView(): void
    {
        $stats = new UserStat();
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);
        $user->method('getStats')
            ->willReturn($stats);
        $progression->setUser($user);

        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->spiceViewRepo->method('countDistinctSpicesByUser')
            ->willReturn(1);
        // countByUser drives totalSpicesRead in the idempotent handler.
        $this->spiceViewRepo->method('countByUser')
            ->willReturn(1);
        $this->spicesRepo->method('find')
            ->willReturn(null);
        $this->manager->method('getOrCreateStats')
            ->willReturn($stats);

        ($this->handler)(new SpiceReadEvent(1, 42, true));

        self::assertSame(1, $progression->getTotalSpicesRead());
    }

    public function testInvokeDoesNotIncrementSpicesReadWhenNotNewView(): void
    {
        $stats = new UserStat();
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);
        $user->method('getStats')
            ->willReturn($stats);
        $progression->setUser($user);

        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->spiceViewRepo->method('countDistinctSpicesByUser')
            ->willReturn(1);
        $this->spicesRepo->method('find')
            ->willReturn(null);
        $this->manager->method('getOrCreateStats')
            ->willReturn($stats);

        ($this->handler)(new SpiceReadEvent(1, 42, false));

        self::assertSame(0, $progression->getTotalSpicesRead());
    }

    public function testInvokeCallsManagerProcessWithContext(): void
    {
        $stats = new UserStat();
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);
        $user->method('getStats')
            ->willReturn($stats);
        $progression->setUser($user);

        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->spiceViewRepo->method('countDistinctSpicesByUser')
            ->willReturn(1);
        $this->spicesRepo->method('find')
            ->willReturn(null);
        $this->manager->method('getOrCreateStats')
            ->willReturn($stats);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'spice_read', [
                'isNewView' => true,
            ]);

        ($this->handler)(new SpiceReadEvent(1, 42, true));
    }

    public function testInvokeCreatesProgressionWhenNull(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn(null);
        $user->method('getStats')
            ->willReturn(new UserStat());

        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->spiceViewRepo->method('countDistinctSpicesByUser')
            ->willReturn(1);
        $this->spicesRepo->method('find')
            ->willReturn(null);
        $this->manager->method('getOrCreateStats')
            ->willReturn(new UserStat());

        $user->expects(self::once())->method('setProgression');
        $this->em->expects(self::atLeastOnce())->method('persist');

        ($this->handler)(new SpiceReadEvent(1, 42, true));
    }

    public function testInvokeSkipsStatsWhenGamificationDisabled(): void
    {
        $progression = new UserProgression();
        $progression->disableGamification();
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);
        $progression->setUser($user);

        $this->usersRepo->method('find')
            ->willReturn($user);

        $this->spiceViewRepo->expects(self::never())->method('countDistinctSpicesByUser');
        $this->manager->expects(self::never())->method('getOrCreateStats');
        $this->manager->expects(self::once())->method('process');

        ($this->handler)(new SpiceReadEvent(1, 42, true));
    }

    public function testInvokeUpdatesAromaticGroupInUserStat(): void
    {
        $stats = new UserStat();
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);
        $user->method('getStats')
            ->willReturn($stats);
        $progression->setUser($user);

        $group = $this->createMock(AromaticGroups::class);
        $group->method('getId')
            ->willReturn(5);

        $spice = $this->createMock(Spices::class);
        $spice->method('getAromaticGroups')
            ->willReturn($group);

        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->spiceViewRepo->method('countDistinctSpicesByUser')
            ->willReturn(1);
        $this->spicesRepo->method('find')
            ->willReturn($spice);
        $this->manager->method('getOrCreateStats')
            ->willReturn($stats);

        ($this->handler)(new SpiceReadEvent(1, 42, true));

        self::assertContains(5, $stats->getVisitedAromaticGroups());
    }
}
