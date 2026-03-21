<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
class GameSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Users $user = null;

    #[ORM\Column(type: 'string', enumType: GameMode::class)]
    private GameMode $gameMode;

    #[ORM\Column(type: 'string', enumType: GameDifficulty::class)]
    private GameDifficulty $difficulty;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $score = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $correctAnswers = 0;

    #[ORM\Column(options: [
        'default' => 10,
    ])]
    private int $totalQuestions = 10;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /**
     * @var Collection<int, GameQuestion>
     */
    #[ORM\OneToMany(mappedBy: 'session', targetEntity: GameQuestion::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    #[ORM\OrderBy([
        'questionIndex' => 'ASC',
    ])]
    private Collection $questions;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->startedAt = new \DateTimeImmutable();
    }

    /**
     * Accuracy percentage (0.0–100.0).
     */
    public float $accuracy {
        get {
            if ($this->totalQuestions === 0) {
                return 0.0;
            }

            return round($this->correctAnswers / $this->totalQuestions * 100, 1);
        }
    }

    /**
     * Whether the session has been finished.
     */
    public bool $isFinished {
        get => $this->finishedAt !== null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGameMode(): GameMode
    {
        return $this->gameMode;
    }

    public function setGameMode(GameMode $gameMode): static
    {
        $this->gameMode = $gameMode;

        return $this;
    }

    public function getDifficulty(): GameDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(GameDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getCorrectAnswers(): int
    {
        return $this->correctAnswers;
    }

    public function incrementCorrectAnswers(): static
    {
        ++$this->correctAnswers;

        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): static
    {
        $this->totalQuestions = $totalQuestions;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function finish(): static
    {
        $this->finishedAt = new \DateTimeImmutable();
        $elapsed = $this->finishedAt->getTimestamp() - $this->startedAt->getTimestamp();
        $this->durationSeconds = max(0, $elapsed);

        return $this;
    }

    /**
     * @return Collection<int, GameQuestion>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(GameQuestion $question): static
    {
        if (! $this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setSession($this);
        }

        return $this;
    }

    public function getCurrentQuestionIndex(): int
    {
        return $this->questions->count();
    }

    public function getAccuracy(): float
    {
        return $this->accuracy;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }
}
