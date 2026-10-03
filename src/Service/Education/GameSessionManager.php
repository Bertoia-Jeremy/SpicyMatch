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
use App\Service\Clock\GameDay;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class GameSessionManager
{
    public const int MAX_DAILY_SESSIONS_FREE = 5;

    public const int MAX_DAILY_SESSIONS_PREMIUM = 10;

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
        private readonly GameDay $gameDay,
        private readonly DailyChallengeResolver $dailyChallenge,
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

            if ($this->sessionRepository->countStartedSince($user, $this->gameDay->today(), $mode) >= $maxDaily) {
                throw new \RuntimeException(sprintf('Limite quotidienne atteinte (%d sessions par jour).', $maxDaily));
            }

            return $create();
        });
    }

    public function countTodaySessions(Users $user, GameMode $mode): int
    {
        return $this->sessionRepository->countStartedSince($user, $this->gameDay->today(), $mode);
    }

    /**
     * @return array<string, int>
     */
    public function countTodaySessionsGrouped(Users $user): array
    {
        return $this->sessionRepository->countStartedSinceGrouped($user, $this->gameDay->today());
    }

    public function isDailyBonusAvailable(?Users $user): bool
    {
        return ! $user instanceof Users
            || ! $this->sessionRepository->hasDailyBonusSince($user, $this->gameDay->today());
    }

    public function qualifiesForDailyBonus(Users $user, GameMode $mode): bool
    {
        return $this->dailyChallenge->forUser($user) === $mode
            && ! $this->sessionRepository->hasDailyBonusSince($user, $this->gameDay->today());
    }

    private function applyScore(GameSession $session, int $baseXp, bool $dailyBonus): void
    {
        $session->setDailyBonus($dailyBonus);
        $session->setScore($dailyBonus ? $baseXp * 2 : $baseXp);
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
     * @param array<string, mixed> $storedQuestion
     * @return array{correct: bool, finished: bool, xpEarned: int|null}
     */
    public function answerQuestion(
        GameSession $session,
        array $storedQuestion,
        string $answer,
        ?int $timeSpentMs = null,
    ): array {
        $correctAnswer = (string) ($storedQuestion['correctAnswer'] ?? '');
        $isCorrect = $correctAnswer !== '' && $answer === $correctAnswer;

        $question = new GameQuestion();
        $question->setQuestionIndex($session->getCurrentQuestionIndex());
        $question->setQuestionData([
            ...$storedQuestion,
            'questionSpiceId' => $this->positiveInt($storedQuestion['baseSpice']['id'] ?? null),
            'correctSpiceId' => $this->positiveInt($storedQuestion['correctSpiceId'] ?? null),
            'givenSpiceId' => $this->optionIdFor($storedQuestion['options'] ?? [], $answer),
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

    private function optionIdFor(mixed $options, string $answer): ?int
    {
        if (! \is_array($options)) {
            return null;
        }

        foreach ($options as $option) {
            if (\is_array($option) && ($option['name'] ?? null) === $answer) {
                return $this->positiveInt($option['id'] ?? null);
            }
        }

        return null;
    }

    private function positiveInt(mixed $value): ?int
    {
        return \is_int($value) && $value > 0 ? $value : null;
    }

    private function finishSession(GameSession $session): int
    {
        $user = $session->getUser() ?? throw new \LogicException('Game session without user.');

        $this->em->wrapInTransaction(function () use ($session, $user): void {
            if ($this->em->contains($user)) {
                $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            }

            $session->finish();
            $this->applyScore(
                $session,
                $this->calculateXp($session),
                $this->qualifiesForDailyBonus($user, $session->getGameMode()),
            );
        });

        $xpEarned = $session->getScore();

        $this->bus->dispatch(new GameCompletedEvent(
            userId: $user->getId(),
            sessionId: $session->getId(),
            gameMode: $session->getGameMode()
                ->value,
            correctAnswers: $session->getCorrectAnswers(),
            totalQuestions: $session->getTotalQuestions(),
            xpEarned: $xpEarned,
            dailyBonus: $session->isDailyBonus(),
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

            $this->applyScore(
                $session,
                $overrideScore !== null
                    ? $this->convertGamePoints($overrideScore, $difficulty)
                    : $this->calculateXp($session),
                $this->qualifiesForDailyBonus($user, $mode),
            );

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
            dailyBonus: $session->isDailyBonus(),
        ));

        return $session;
    }

    /**
     * @param list<array{questionIndex: int, prompt: string, correctAnswer: string, answerGiven: string, isCorrect: bool, questionSpiceId?: ?int, correctSpiceId?: ?int, givenSpiceId?: ?int}> $questionsData
     */
    public function addQuestionsToSession(GameSession $gameSession, array $questionsData): void
    {
        foreach ($questionsData as $qData) {
            $gq = new GameQuestion();
            $gq->setQuestionIndex($qData['questionIndex']);
            $gq->setQuestionData([
                'prompt' => $qData['prompt'],
                'correctAnswer' => $qData['correctAnswer'],
                'questionSpiceId' => $this->positiveInt($qData['questionSpiceId'] ?? null),
                'correctSpiceId' => $this->positiveInt($qData['correctSpiceId'] ?? null),
                'givenSpiceId' => $this->positiveInt($qData['givenSpiceId'] ?? null),
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
