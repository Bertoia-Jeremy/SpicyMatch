<?php

namespace App\Entity;

use App\Repository\SpicyMatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpicyMatchRepository::class)]
class SpicyMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'spicyMatches')]
    #[ORM\JoinColumn(nullable: false, name: 'user_id')]
    private ?Users $user_id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $spices_ids = '';

    #[ORM\Column(nullable: true)]
    private ?int $nb_spice = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?Users
    {
        return $this->user_id;
    }

    public function setUserId(?Users $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getSpicesIds(): string
    {
        return $this->spices_ids;
    }

    public function setSpicesIds(string $spices_ids): static
    {
        $this->spices_ids = $spices_ids;

        return $this;
    }

    public function getNbSpice(): ?int
    {
        return $this->nb_spice;
    }

    public function setNbSpice(?int $nb_spice): static
    {
        $this->nb_spice = $nb_spice;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeInterface $deleted_at): static
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }
}
