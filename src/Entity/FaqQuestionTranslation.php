<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\FaqQuestionTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FaqQuestionTranslationRepository::class)]
#[ORM\Table(name: 'faq_question_translation')]
#[ORM\UniqueConstraint(name: 'uniq_faq_question_locale', columns: ['faq_question_id', 'locale'])]
#[ORM\Index(name: 'idx_faq_question_translation_locale', columns: ['locale'])]
class FaqQuestionTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FaqQuestion::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'faq_question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?FaqQuestion $faqQuestion = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $question = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $answer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFaqQuestion(): ?FaqQuestion
    {
        return $this->faqQuestion;
    }

    public function setFaqQuestion(?FaqQuestion $faqQuestion): static
    {
        $this->faqQuestion = $faqQuestion;

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

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }
}
