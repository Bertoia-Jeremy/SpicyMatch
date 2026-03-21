<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Gamification\GamificationManagerInterface;
use App\Message\FavoriteToggledEvent;
use App\MessageHandler\FavoriteGamificationHandler;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class FavoriteGamificationHandlerTest extends TestCase
{
    private UsersRepository&MockObject $usersRepo;

    private SpicyMatchHistoryRepository&MockObject $historyRepo;

    private GamificationManagerInterface&MockObject $manager;

    private EntityManagerInterface&MockObject $em;

    private FavoriteGamificationHandler $handler;

    protected function setUp(): void
    {
        $this->usersRepo = $this->createMock(UsersRepository::class);
        $this->historyRepo = $this->createMock(SpicyMatchHistoryRepository::class);
        $this->manager = $this->createMock(GamificationManagerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->handler = new FavoriteGamificationHandler(
            $this->usersRepo,
            $this->historyRepo,
            $this->manager,
            $this->em
        );
    }

    public function testReturnsEarlyWhenUserNotFound(): void
    {
        $this->usersRepo->expects(self::once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new FavoriteToggledEvent(999));
    }

    public function testDelegatesWithFavoriteCountContext(): void
    {
        $progression = new UserProgression();
        $user = $this->createConfiguredMock(Users::class, [
            'getProgression' => $progression,
        ]);

        $this->usersRepo->method('find')
            ->willReturn($user);

        $this->historyRepo->expects(self::once())
            ->method('countFavoritesByUser')
            ->with($user)
            ->willReturn(3);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'favorite_toggled', [
                'favoriteCount' => 3,
            ]);

        ($this->handler)(new FavoriteToggledEvent(1));
    }

    public function testHandlesZeroFavoritesGracefully(): void
    {
        $progression = new UserProgression();
        $user = $this->createConfiguredMock(Users::class, [
            'getProgression' => $progression,
        ]);

        $this->usersRepo->method('find')
            ->willReturn($user);

        $this->historyRepo->method('countFavoritesByUser')
            ->willReturn(0);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'favorite_toggled', [
                'favoriteCount' => 0,
            ]);

        ($this->handler)(new FavoriteToggledEvent(1));
    }
}
