<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Gamification\GamificationManagerInterface;
use App\Message\MatchSavedEvent;
use App\MessageHandler\GamificationHandler;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
final class GamificationHandlerTest extends TestCase
{
    private SpicyMatchHistoryRepository&MockObject $historyRepo;
    private GamificationManagerInterface&MockObject $manager;
    private EntityManagerInterface&MockObject $em;
    private ProcessedGamificationEventRepository&MockObject $processedEvents;
    private GamificationHandler $handler;

    protected function setUp(): void
    {
        $this->historyRepo = $this->createMock(SpicyMatchHistoryRepository::class);
        $this->manager = $this->createMock(GamificationManagerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->processedEvents = $this->createMock(ProcessedGamificationEventRepository::class);
        // Default: ledger claim always succeeds — override per-test for idempotency scenarios.
        $this->processedEvents->method('claim')
            ->willReturn(true);

        $this->handler = new GamificationHandler(
            $this->historyRepo,
            $this->manager,
            $this->em,
            new NullLogger(),
            $this->processedEvents,
        );
    }

    public function testInvokeReturnsEarlyWhenHistoryNotFound(): void
    {
        $this->historyRepo->method('find')
            ->willReturn(null);
        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new MatchSavedEvent(999, 1));
    }

    public function testInvokeCreatesUserProgressionIfNull(): void
    {
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn(new UserStat());

        $spicyMatch = $this->createMock(SpicyMatch::class);
        $spicyMatch->method('getUser')
            ->willReturn($user);

        $history = $this->createMock(SpicyMatchHistory::class);
        $history->method('getSpicyMatch')
            ->willReturn($spicyMatch);

        $this->historyRepo->method('find')
            ->willReturn($history);
        $this->historyRepo->method('countByUser')
            ->willReturn(0);
        $this->historyRepo->method('countDistinctSpicesByUser')
            ->willReturn(0);

        // Progression creation is now delegated to the manager.
        $this->manager->expects(self::once())
            ->method('getOrCreateProgression')
            ->with($user)
            ->willReturn($progression);

        ($this->handler)(new MatchSavedEvent(1, 1));
    }

    public function testInvokeSetsMatchCountFromDb(): void
    {
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn(new UserStat());
        $progression->setUser($user);
        $this->manager->method('getOrCreateProgression')
            ->willReturn($progression);

        $spicyMatch = $this->createMock(SpicyMatch::class);
        $spicyMatch->method('getUser')
            ->willReturn($user);

        $history = $this->createMock(SpicyMatchHistory::class);
        $history->method('getSpicyMatch')
            ->willReturn($spicyMatch);

        $this->historyRepo->method('find')
            ->willReturn($history);
        $this->historyRepo->method('countByUser')
            ->willReturn(7);
        $this->historyRepo->method('countDistinctSpicesByUser')
            ->willReturn(3);

        ($this->handler)(new MatchSavedEvent(1, 1));

        self::assertSame(7, $progression->getTotalMatches());
        self::assertSame(3, $progression->getUniqueSpicesUsed());
    }

    public function testInvokeReturnsEarlyWhenUserIsNull(): void
    {
        $spicyMatch = $this->createMock(SpicyMatch::class);
        $spicyMatch->method('getUser')
            ->willReturn(null);

        $history = $this->createMock(SpicyMatchHistory::class);
        $history->method('getSpicyMatch')
            ->willReturn($spicyMatch);

        $this->historyRepo->method('find')
            ->willReturn($history);

        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new MatchSavedEvent(1, 1));
    }

    public function testInvokeSkipsCountsWhenGamificationDisabled(): void
    {
        $progression = new UserProgression();
        $progression->disableGamification();
        $user = $this->createMock(Users::class);
        $progression->setUser($user);
        $this->manager->method('getOrCreateProgression')
            ->willReturn($progression);

        $spicyMatch = $this->createMock(SpicyMatch::class);
        $spicyMatch->method('getUser')
            ->willReturn($user);

        $history = $this->createMock(SpicyMatchHistory::class);
        $history->method('getSpicyMatch')
            ->willReturn($spicyMatch);

        $this->historyRepo->method('find')
            ->willReturn($history);
        $this->historyRepo->expects(self::never())->method('countByUser');

        // process() is still called (manager handles opt-out)
        $this->manager->expects(self::once())->method('process');

        ($this->handler)(new MatchSavedEvent(1, 1));
    }

    public function testInvokeCallsManagerProcessWithMatchSaved(): void
    {
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn(new UserStat());
        $progression->setUser($user);
        $this->manager->method('getOrCreateProgression')
            ->willReturn($progression);

        $spicyMatch = $this->createMock(SpicyMatch::class);
        $spicyMatch->method('getUser')
            ->willReturn($user);

        $history = $this->createMock(SpicyMatchHistory::class);
        $history->method('getSpicyMatch')
            ->willReturn($spicyMatch);

        $this->historyRepo->method('find')
            ->willReturn($history);
        $this->historyRepo->method('countByUser')
            ->willReturn(1);
        $this->historyRepo->method('countDistinctSpicesByUser')
            ->willReturn(1);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'match_saved');

        ($this->handler)(new MatchSavedEvent(1, 1));
    }

    // ── Messenger retry idempotency ──────────────────────────────────────────

    public function testInvokeShortCircuitsOnDuplicateEventWithoutCallingManager(): void
    {
        // Build a dedicated handler whose ledger rejects the claim → simulates retry.
        $processedEvents = $this->createMock(ProcessedGamificationEventRepository::class);
        $processedEvents->method('claim')
            ->willReturn(false);

        $handler = new GamificationHandler(
            $this->historyRepo,
            $this->manager,
            $this->em,
            new NullLogger(),
            $processedEvents,
        );

        $user = $this->createMock(Users::class);
        $spicyMatch = $this->createMock(SpicyMatch::class);
        $spicyMatch->method('getUser')
            ->willReturn($user);

        $history = $this->createMock(SpicyMatchHistory::class);
        $history->method('getSpicyMatch')
            ->willReturn($spicyMatch);

        $this->historyRepo->method('find')
            ->willReturn($history);

        // Strongest assertion: a duplicate delivery MUST NOT re-invoke the gamification pipeline.
        $this->manager->expects(self::never())->method('process');
        $this->em->expects(self::never())->method('flush');

        ($handler)(new MatchSavedEvent(1, 1));
    }
}
