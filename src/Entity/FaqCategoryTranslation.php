<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\FaqCategoryTranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FaqCategoryTranslationRepository::class)]
#[ORM\Table(name: 'faq_category_translation')]
#[ORM\UniqueConstraint(name: 'uniq_faq_category_locale', columns: ['faq_category_id', 'locale'])]
#[ORM\Index(name: 'idx_faq_category_translation_locale', columns: ['locale'])]
class FaqCategoryTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FaqCategory::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'faq_category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?FaqCategory $category = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?FaqCategory
    {
        return $this->category;
    }

    public function setCategory(?FaqCategory $category): static
    {
        $this->category = $category;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
