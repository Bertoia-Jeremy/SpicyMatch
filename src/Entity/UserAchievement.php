<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserAchievementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAchievementRepository::class)]
#[ORM\UniqueConstraint(fields: ['userProgression', 'achievement'])]
class UserAchievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userAchievements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserProgression $userProgression = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Achievement $achievement = null;

    #[ORM\Column]
    private \DateTimeImmutable $unlockedAt;

    public function __construct()
    {
        $this->unlockedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserProgression(): ?UserProgression
    {
        return $this->userProgression;
    }

    public function setUserProgression(?UserProgression $userProgression): static
    {
        $this->userProgression = $userProgression;

        return $this;
    }

    public function getAchievement(): ?Achievement
    {
        return $this->achievement;
    }

    public function setAchievement(?Achievement $achievement): static
    {
        $this->achievement = $achievement;

        return $this;
    }

    public function getUnlockedAt(): \DateTimeImmutable
    {
        return $this->unlockedAt;
    }
}
