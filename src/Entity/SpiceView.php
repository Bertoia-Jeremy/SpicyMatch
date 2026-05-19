<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpiceViewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpiceViewRepository::class)]
#[ORM\Table(name: 'spice_view')]
#[ORM\UniqueConstraint(name: 'spice_view_user_spice_day', columns: ['user_id', 'spice_id', 'viewed_day'])]
#[ORM\Index(name: 'idx_sv_user_day', columns: ['user_id', 'viewed_day'])]
class SpiceView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Users $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Spices $spice = null;

    #[ORM\Column]
    private \DateTimeImmutable $viewedAt;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $viewedDay;

    public function __construct(Users $user, Spices $spice)
    {
        $this->user = $user;
        $this->spice = $spice;
        $this->viewedAt = new \DateTimeImmutable();
        $this->viewedDay = new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function getSpice(): ?Spices
    {
        return $this->spice;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function getViewedDay(): \DateTimeImmutable
    {
        return $this->viewedDay;
    }
}
