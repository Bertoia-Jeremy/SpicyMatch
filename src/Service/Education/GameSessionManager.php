<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\GameQuestion;
use App\Entity\GameSession;
use App\Entity\Spices;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Message\GameCompletedEvent;
use App\Repository\GameSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class GameSessionManager
{
    private const int MAX_DAILY_SESSIONS = 5;
    private const int REDUCED_XP_THRESHOLD = 3;

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
        ?Spices $targetSpice = null,
    ): GameSession {
        $todayCount = $this->sessionRepository->countTodayByUser($user, $mode);
        if ($todayCount >= self::MAX_DAILY_SESSIONS) {
            throw new \RuntimeException(sprintf(
                'Limite quotidienne atteinte (%d sessions %s par jour).',
                self::MAX_DAILY_SESSIONS,
                $mode->label(),
            ));
        }

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode($mode);
        $session->setDifficulty($difficulty);

        if ($targetSpice !== null) {
            $session->setTargetSpice($targetSpice);
        }

        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    /**
     * Generate the next question for a session.
     *
     * @return array<string, mixed>|null null if session is finished or generation fails
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

        // Collect already-used base spice IDs to avoid repeats
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
     * Record an answer and return whether it was correct.
     *
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

    /**
     * Create and immediately finish a GameSession from Live Component data.
     * No GameQuestion rows — only the session summary is persisted.
     */
    public function createFinishedSession(
        Users $user,
        GameMode $mode,
        GameDifficulty $difficulty,
        int $correctAnswers,
        int $totalQuestions,
        ?int $durationSeconds = null,
        ?Spices $targetSpice = null,
        ?int $overrideScore = null,
    ): GameSession {
        $todayCount = $this->sessionRepository->countTodayByUser($user, $mode);
        if ($todayCount >= self::MAX_DAILY_SESSIONS) {
            throw new \RuntimeException(sprintf(
                'Limite quotidienne atteinte (%d sessions %s par jour).',
                self::MAX_DAILY_SESSIONS,
                $mode->label(),
            ));
        }

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode($mode);
        $session->setDifficulty($difficulty);
        $session->setTotalQuestions($totalQuestions);

        if ($targetSpice !== null) {
            $session->setTargetSpice($targetSpice);
        }

        for ($i = 0; $i < $correctAnswers; ++$i) {
            $session->incrementCorrectAnswers();
        }

        $session->finish();

        if ($durationSeconds !== null) {
            $session->setDurationSeconds($durationSeconds);
        }

        if ($overrideScore !== null) {
            // Reuse $todayCount from the limit check above (session not yet flushed — count is stable).
            $xpEarned = $todayCount > self::REDUCED_XP_THRESHOLD
                ? (int) round($overrideScore * 0.5)
                : $overrideScore;
        } else {
            $xpEarned = $this->calculateXp($session);
        }

        $session->setScore($xpEarned);

        $this->em->persist($session);
        $this->em->flush();

        $this->bus->dispatch(new GameCompletedEvent(
            userId: $user->getId(),
            sessionId: $session->getId(),
            gameMode: $mode->value,
            correctAnswers: $correctAnswers,
            totalQuestions: $totalQuestions,
            xpEarned: $xpEarned,
        ));

        return $session;
    }

    /**
     * Persists per-question answer history for LC game modes that use createFinishedSession().
     *
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

        // Reduce XP for sessions beyond the daily threshold
        $todayCount = $this->sessionRepository->countTodayByUser($session->getUser(), $session->getGameMode());

        if ($todayCount > self::REDUCED_XP_THRESHOLD) {
            $base *= 0.5;
        }

        return (int) round($base);
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
