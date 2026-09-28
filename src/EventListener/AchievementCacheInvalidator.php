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
final readonly class AchievementCacheInvalidator
{
    public function __construct(
        private AchievementRepository $achievementRepository,
    ) {
    }

    public function postPersist(): void
    {
        $this->achievementRepository->resetEnabledCache();
    }

    public function postUpdate(): void
    {
        $this->achievementRepository->resetEnabledCache();
    }

    public function postRemove(): void
    {
        $this->achievementRepository->resetEnabledCache();
    }
}
