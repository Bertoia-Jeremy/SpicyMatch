<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GameQuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameQuestionRepository::class)]
class GameQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GameSession::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameSession $session = null;

    #[ORM\Column]
    private int $questionIndex;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $questionData = [];

    #[ORM\Column(nullable: true)]
    private ?string $answerGiven = null;

    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $isCorrect = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $answeredAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeSpentMs = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?GameSession
    {
        return $this->session;
    }

    public function setSession(?GameSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getQuestionIndex(): int
    {
        return $this->questionIndex;
    }

    public function setQuestionIndex(int $questionIndex): static
    {
        $this->questionIndex = $questionIndex;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuestionData(): array
    {
        return $this->questionData;
    }

    /**
     * @param array<string, mixed> $questionData
     */
    public function setQuestionData(array $questionData): static
    {
        $this->questionData = $questionData;

        return $this;
    }

    public function getAnswerGiven(): ?string
    {
        return $this->answerGiven;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function getAnsweredAt(): ?\DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function getTimeSpentMs(): ?int
    {
        return $this->timeSpentMs;
    }

    public function answer(string $answer, bool $correct, ?int $timeSpentMs = null): static
    {
        $this->answerGiven = $answer;
        $this->isCorrect = $correct;
        $this->answeredAt = new \DateTimeImmutable();
        $this->timeSpentMs = $timeSpentMs;

        return $this;
    }
}
