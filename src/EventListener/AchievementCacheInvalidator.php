<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Achievement;
use App\Repository\AchievementRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Achievement::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Achievement::class)]
#[AsEntityListener(event: Events::postRemove, entity: Achievement::class)]
final class AchievementCacheInvalidator
{
    public function __construct(
        private readonly AchievementRepository $achievementRepository,
    ) {
    }

    public function postPersist(Achievement $achievement): void
    {
        $this->achievementRepository->resetEnabledCache();
    }

    public function postUpdate(Achievement $achievement): void
    {
        $this->achievementRepository->resetEnabledCache();
    }

    public function postRemove(Achievement $achievement): void
    {
        $this->achievementRepository->resetEnabledCache();
    }
}
