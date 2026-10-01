<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Enum\ContentKind;
use App\Gamification\GamificationManagerInterface;
use App\Message\ContentReadEvent;
use App\MessageHandler\ContentReadGamificationHandler;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ContentReadGamificationHandlerTest extends TestCase
{
    private GamificationManagerInterface&MockObject $manager;

    private UserStat $stats;

    private UserProgression $progression;

    private ContentReadGamificationHandler $handler;

    protected function setUp(): void
    {
        $user = new Users();
        $this->stats = new UserStat();
        $this->progression = new UserProgression();
        $this->progression->setUser($user);

        $usersRepo = $this->createStub(UsersRepository::class);
        $usersRepo->method('find')
            ->willReturn($user);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')
            ->willReturnCallback(fn (callable $callback) => $callback($em));
        $this->manager = $this->createMock(GamificationManagerInterface::class);
        $this->manager->method('getOrCreateProgression')
            ->willReturn($this->progression);
        $this->manager->method('getOrCreateStats')
            ->willReturn($this->stats);

        $this->handler = new ContentReadGamificationHandler($usersRepo, $this->manager, $em);
    }

    public function testFirstReadOfAKindIsRecordedAndProcessed(): void
    {
        $this->manager->expects(self::once())
            ->method('process')
            ->with($this->progression, 'content_read', [
                'kind' => 'compound',
            ]);

        ($this->handler)(new ContentReadEvent(1, ContentKind::COMPOUND));

        self::assertTrue($this->stats->hasReadContentKind(ContentKind::COMPOUND));
    }

    public function testKindAlreadyReadIsIgnored(): void
    {
        $this->stats->recordReadContentKind(ContentKind::FLAVOR);
        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new ContentReadEvent(1, ContentKind::FLAVOR));
    }

    public function testNothingIsRecordedWhenGamificationIsOff(): void
    {
        $this->progression->disableGamification();
        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new ContentReadEvent(1, ContentKind::COMPOUND));

        self::assertFalse($this->stats->hasReadContentKind(ContentKind::COMPOUND));
    }
}
