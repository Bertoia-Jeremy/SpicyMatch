<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslatableInterface;
use App\Repository\FaqQuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FaqQuestionRepository::class)]
#[ORM\Table(name: 'faq_question')]
#[ORM\Index(name: 'idx_faq_question_published_position', columns: ['is_published', 'position'])]
class FaqQuestion implements TranslatableInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FaqCategory::class)]
    #[ORM\JoinColumn(name: 'faq_category_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    private ?FaqCategory $category = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $question = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $answer = null;

    #[ORM\Column(type: 'integer', options: [
        'default' => 0,
    ])]
    private int $position = 0;

    #[ORM\Column(name: 'is_published', type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $published = false;

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class)]
    #[ORM\JoinTable(name: 'faq_question_spice')]
    #[ORM\JoinColumn(name: 'faq_question_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'spices_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $spices;

    /**
     * @var Collection<int, FaqQuestionTranslation>
     */
    #[ORM\OneToMany(targetEntity: FaqQuestionTranslation::class, mappedBy: 'faqQuestion', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    #[Assert\Valid]
    #[Assert\Unique(normalizer: static function (FaqQuestionTranslation $translation): string { return $translation->getLocale(); }, errorPath: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

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

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    /**
     * @return Collection<int, Spices>
     */
    public function getSpices(): Collection
    {
        return $this->spices;
    }

    public function addSpice(Spices $spice): static
    {
        if (! $this->spices->contains($spice)) {
            $this->spices->add($spice);
        }

        return $this;
    }

    public function removeSpice(Spices $spice): static
    {
        $this->spices->removeElement($spice);

        return $this;
    }

    /**
     * @return Collection<int, FaqQuestionTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(FaqQuestionTranslation $translation): static
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setFaqQuestion($this);
        }

        return $this;
    }

    public function removeTranslation(FaqQuestionTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getFaqQuestion() === $this) {
            $translation->setFaqQuestion(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?FaqQuestionTranslation
    {
        if ($locale === 'fr') {
            return null;
        }

        foreach ($this->translations as $t) {
            if ($t->getLocale() === $locale) {
                return $t;
            }
        }

        return null;
    }

    public function getLocalizedQuestion(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getQuestion() ?? $this->question;
    }

    public function getLocalizedAnswer(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getAnswer() ?? $this->answer;
    }

    public function __toString(): string
    {
        return (string) $this->question;
    }
}
