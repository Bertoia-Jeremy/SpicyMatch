<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Service\GamificationManager;
use App\Service\GamificationManagerProxy;
use App\Service\NullGamificationManager;
use PHPUnit\Framework\TestCase;

final class GamificationManagerProxyTest extends TestCase
{
    private GamificationManager $realManager;
    private NullGamificationManager $nullManager;
    private GamificationManagerProxy $proxy;

    protected function setUp(): void
    {
        $this->realManager = $this->createMock(GamificationManager::class);
        $this->nullManager = $this->createMock(NullGamificationManager::class);
        $this->proxy = new GamificationManagerProxy($this->realManager, $this->nullManager);
    }

    public function testProcessDelegatesToRealWhenEnabled(): void
    {
        $progression = new UserProgression();
        $progression->enableGamification();

        $this->realManager->expects(self::once())
            ->method('process')
            ->with($progression, 'test_event', []);

        $this->nullManager->expects(self::never())
            ->method('process');

        $this->proxy->process($progression, 'test_event');
    }

    public function testProcessDelegatesToNullWhenDisabled(): void
    {
        $progression = new UserProgression();
        $progression->disableGamification();

        $this->realManager->expects(self::never())
            ->method('process');

        $this->nullManager->expects(self::once())
            ->method('process')
            ->with($progression, 'test_event', []);

        $this->proxy->process($progression, 'test_event');
    }

    public function testGetOrCreateStatsDelegatesToRealWhenEnabled(): void
    {
        $user = new Users();
        $progression = new UserProgression();
        $progression->enableGamification();
        $user->setProgression($progression);

        $this->realManager->expects(self::once())
            ->method('getOrCreateStats')
            ->with($user);

        $this->proxy->getOrCreateStats($user);
    }

    public function testGetOrCreateStatsDelegatesToRealWhenNoProgression(): void
    {
        $user = new Users();
        // No progression set → defaults to enabled (true)

        $this->realManager->expects(self::once())
            ->method('getOrCreateStats')
            ->with($user);

        $this->proxy->getOrCreateStats($user);
    }

    public function testProcessForwardsContextToDelegate(): void
    {
        $progression = new UserProgression();
        $context = [
            'key' => 'value',
            'isNewView' => true,
        ];

        $this->realManager->expects(self::once())
            ->method('process')
            ->with($progression, 'spice_read', $context);

        $this->proxy->process($progression, 'spice_read', $context);
    }

    public function testGetOrCreateStatsDelegatesToNullWhenDisabled(): void
    {
        $user = new Users();
        $progression = new UserProgression();
        $progression->disableGamification();
        $user->setProgression($progression);

        $this->nullManager->expects(self::once())
            ->method('getOrCreateStats')
            ->with($user);

        $this->proxy->getOrCreateStats($user);
    }
}
