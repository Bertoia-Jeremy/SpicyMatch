<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\GameQuestion;
use App\Entity\GameSession;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use PHPUnit\Framework\TestCase;

class GameSessionTest extends TestCase
{
    private GameSession $session;

    protected function setUp(): void
    {
        $this->session = new GameSession();
        $this->session->setGameMode(GameMode::QCM);
        $this->session->setDifficulty(GameDifficulty::EASY);
    }

    public function testAccuracyWithNoQuestions(): void
    {
        self::assertSame(0.0, $this->session->accuracy);
    }

    public function testAccuracyAfterCorrectAnswers(): void
    {
        $this->session->setTotalQuestions(4);
        $this->session->incrementCorrectAnswers();
        $this->session->incrementCorrectAnswers();
        $this->session->incrementCorrectAnswers();

        self::assertSame(75.0, $this->session->accuracy);
    }

    public function testIsFinishedDefaultsFalse(): void
    {
        self::assertFalse($this->session->isFinished);
    }

    public function testFinishSetsFinishedAt(): void
    {
        $this->session->finish();

        self::assertTrue($this->session->isFinished);
        self::assertNotNull($this->session->getFinishedAt());
        self::assertIsInt($this->session->getDurationSeconds());
    }

    public function testAddQuestion(): void
    {
        $q = new GameQuestion();
        $q->setQuestionIndex(0);
        $this->session->addQuestion($q);

        self::assertCount(1, $this->session->getQuestions());
        self::assertSame($this->session, $q->getSession());
    }

    public function testCurrentQuestionIndex(): void
    {
        self::assertSame(0, $this->session->getCurrentQuestionIndex());

        $q1 = new GameQuestion();
        $q1->setQuestionIndex(0);
        $this->session->addQuestion($q1);

        self::assertSame(1, $this->session->getCurrentQuestionIndex());
    }
}
