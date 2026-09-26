<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\SpiceDuoTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SpiceDuoTranslationRepository::class)]
#[ORM\Table(name: 'spice_duo_translation')]
#[ORM\UniqueConstraint(name: 'uniq_spice_duo_locale', columns: ['duo_id', 'locale'])]
#[ORM\Index(name: 'idx_spice_duo_translation_locale', columns: ['locale'])]
class SpiceDuoTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SpiceDuo::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'duo_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SpiceDuo $duo = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $effect = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $science = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $example = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDuo(): ?SpiceDuo
    {
        return $this->duo;
    }

    public function setDuo(?SpiceDuo $duo): static
    {
        $this->duo = $duo;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    public function setReviewed(bool $reviewed): static
    {
        $this->reviewed = $reviewed;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getEffect(): ?string
    {
        return $this->effect;
    }

    public function setEffect(?string $effect): static
    {
        $this->effect = $effect;

        return $this;
    }

    public function getScience(): ?string
    {
        return $this->science;
    }

    public function setScience(?string $science): static
    {
        $this->science = $science;

        return $this;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }

    public function setExample(?string $example): static
    {
        $this->example = $example;

        return $this;
    }
}
