<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\GameQuestion;
use App\Entity\GameSession;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Message\GameCompletedEvent;
use App\Repository\GameSessionRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class GameSessionManager
{
    private const int MAX_DAILY_SESSIONS_FREE = 2;

    private const int MAX_DAILY_SESSIONS_PREMIUM = 5;

    public const int MAX_XP_PER_SESSION = 60;

    public function maxDailySessions(?Users $user): int
    {
        return $user !== null && $user->isPremium()
            ? self::MAX_DAILY_SESSIONS_PREMIUM
            : self::MAX_DAILY_SESSIONS_FREE;
    }

    /**
     * @param iterable<QuestionGeneratorInterface> $generators
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameSessionRepository $sessionRepository,
        private readonly MessageBusInterface $bus,
        private readonly iterable $generators,
    ) {
    }

    public function startSession(
        Users $user,
        GameMode $mode,
        GameDifficulty $difficulty,
    ): GameSession {
        return $this->withDailyQuota($user, $mode, function () use ($user, $mode, $difficulty): GameSession {
            $session = new GameSession();
            $session->setUser($user);
            $session->setGameMode($mode);
            $session->setDifficulty($difficulty);

            $modeQuestions = $mode->totalQuestions();
            if ($modeQuestions !== null) {
                $session->setTotalQuestions($modeQuestions);
            }

            $this->em->persist($session);

            return $session;
        });
    }

    /**
     * @template T
     * @param \Closure(): T $create
     * @return T
     * @throws \RuntimeException
     */
    private function withDailyQuota(Users $user, GameMode $mode, \Closure $create): mixed
    {
        $maxDaily = $this->maxDailySessions($user);

        return $this->em->wrapInTransaction(function () use ($user, $mode, $maxDaily, $create): mixed {
            if ($this->em->contains($user)) {
                $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            }

            if ($this->sessionRepository->countTodayByUser($user, $mode) >= $maxDaily) {
                throw new \RuntimeException(sprintf('Limite quotidienne atteinte (%d sessions par jour).', $maxDaily));
            }

            return $create();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nextQuestion(GameSession $session): ?array
    {
        if ($session->isFinished() || $session->getCurrentQuestionIndex() >= $session->getTotalQuestions()) {
            return null;
        }

        $generator = $this->getGenerator($session->getGameMode());
        if ($generator === null) {
            return null;
        }

        $excludeIds = [];
        foreach ($session->getQuestions() as $q) {
            $data = $q->getQuestionData();
            if (isset($data['baseSpice']['id'])) {
                $excludeIds[] = $data['baseSpice']['id'];
            }
        }

        return $generator->generate($session->getDifficulty(), $excludeIds);
    }

    /**
     * @return array{correct: bool, finished: bool, xpEarned: int|null}
     */
    public function answerQuestion(
        GameSession $session,
        string $answer,
        string $correctAnswer,
        ?int $timeSpentMs = null,
    ): array {
        $isCorrect = $answer === $correctAnswer;

        $question = new GameQuestion();
        $question->setQuestionIndex($session->getCurrentQuestionIndex());
        $question->setQuestionData([
            'answer' => $answer,
            'correctAnswer' => $correctAnswer,
        ]);
        $question->answer($answer, $isCorrect, $timeSpentMs);

        $session->addQuestion($question);

        if ($isCorrect) {
            $session->incrementCorrectAnswers();
        }

        $this->em->persist($question);

        $xpEarned = null;
        $finished = $session->getCurrentQuestionIndex() >= $session->getTotalQuestions();

        if ($finished) {
            $xpEarned = $this->finishSession($session);
        }

        $this->em->flush();

        return [
            'correct' => $isCorrect,
            'finished' => $finished,
            'xpEarned' => $xpEarned,
        ];
    }

    private function finishSession(GameSession $session): int
    {
        $session->finish();

        $xpEarned = $this->calculateXp($session);
        $session->setScore($xpEarned);

        $this->bus->dispatch(new GameCompletedEvent(
            userId: $session->getUser()
                ->getId(),
            sessionId: $session->getId(),
            gameMode: $session->getGameMode()
                ->value,
            correctAnswers: $session->getCorrectAnswers(),
            totalQuestions: $session->getTotalQuestions(),
            xpEarned: $xpEarned,
        ));

        return $xpEarned;
    }

    public function createFinishedSession(
        Users $user,
        GameMode $mode,
        GameDifficulty $difficulty,
        int $correctAnswers,
        int $totalQuestions,
        ?int $durationSeconds = null,
        ?int $overrideScore = null,
    ): GameSession {
        $correctAnswers = max(0, $correctAnswers);
        $totalQuestions = max(0, $totalQuestions);

        $session = $this->withDailyQuota($user, $mode, function () use (
            $user,
            $mode,
            $difficulty,
            $correctAnswers,
            $totalQuestions,
            $durationSeconds,
            $overrideScore,
        ): GameSession {
            $session = new GameSession();
            $session->setUser($user);
            $session->setGameMode($mode);
            $session->setDifficulty($difficulty);
            $session->setTotalQuestions($totalQuestions);

            for ($i = 0; $i < $correctAnswers; ++$i) {
                $session->incrementCorrectAnswers();
            }

            $session->finish();

            if ($durationSeconds !== null) {
                $session->setDurationSeconds(max(0, $durationSeconds));
            }

            $session->setScore($overrideScore !== null
                ? $this->convertGamePoints($overrideScore, $difficulty)
                : $this->calculateXp($session));

            $this->em->persist($session);

            return $session;
        });

        $this->bus->dispatch(new GameCompletedEvent(
            userId: $user->getId(),
            sessionId: $session->getId(),
            gameMode: $mode->value,
            correctAnswers: $correctAnswers,
            totalQuestions: $totalQuestions,
            xpEarned: $session->getScore(),
        ));

        return $session;
    }

    /**
     * @param list<array{questionIndex: int, prompt: string, correctAnswer: string, answerGiven: string, isCorrect: bool}> $questionsData
     */
    public function addQuestionsToSession(GameSession $gameSession, array $questionsData): void
    {
        foreach ($questionsData as $qData) {
            $gq = new GameQuestion();
            $gq->setQuestionIndex($qData['questionIndex']);
            $gq->setQuestionData([
                'prompt' => $qData['prompt'],
                'correctAnswer' => $qData['correctAnswer'],
            ]);
            $gq->answer($qData['answerGiven'], $qData['isCorrect']);
            $gameSession->addQuestion($gq);
            $this->em->persist($gq);
        }

        if ($questionsData !== []) {
            $this->em->flush();
        }
    }

    public function calculateXp(GameSession $session): int
    {
        $base = $session->getCorrectAnswers()
            * $session->getGameMode()
                ->xpPerCorrect()
            * $session->getDifficulty()
                ->xpMultiplier();

        return max(0, min((int) round($base), self::MAX_XP_PER_SESSION));
    }

    public function convertGamePoints(int $points, GameDifficulty $difficulty): int
    {
        return max(0, min((int) round($points * $difficulty->xpMultiplier()), self::MAX_XP_PER_SESSION));
    }

    private function getGenerator(GameMode $mode): ?QuestionGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($mode)) {
                return $generator;
            }
        }

        return null;
    }
}
