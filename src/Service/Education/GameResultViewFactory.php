<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\GameQuestion;
use App\Entity\GameSession;
use App\Entity\Users;
use App\Enum\RecordState;
use App\Enum\ResultLexicon;
use App\Message\GameCompletedEvent;
use App\Repository\GameSessionRepository;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicesRepository;
use App\ValueObject\Education\GameResultView;
use App\ValueObject\Education\MissedSpice;
use App\ValueObject\Education\NextStep;
use App\ValueObject\Education\ResultAnswer;
use App\ValueObject\Education\XpProgress;
use function Symfony\Component\String\u;

final readonly class GameResultViewFactory
{
    public const int MAX_MISSED = 3;

    public const int MAX_ANSWER_LENGTH = 120;

    public function __construct(
        private GameSessionRepository $sessionRepository,
        private GameSessionManager $sessionManager,
        private DifficultyAdvisor $difficultyAdvisor,
        private SpicesRepository $spicesRepository,
        private ProcessedGamificationEventRepository $processedEvents,
    ) {
    }

    public function build(GameSession $session, Users $user, string $locale): GameResultView
    {
        $progression = $user->getProgression();
        $gamification = $progression?->isGamificationEnabled() ?? true;
        $mode = $session->getGameMode();
        $questions = $session->getQuestions()
            ->toArray();
        $cards = $this->spicesRepository->findResultCards($this->spiceIds($questions), $locale);

        $xp = null;
        $previousBest = null;
        $record = null;
        $canReplay = false;

        if ($gamification) {
            $snapshot = $this->processedEvents->findXpSnapshot(
                $user,
                GameCompletedEvent::TYPE,
                GameCompletedEvent::processedKey((int) $session->getId()),
            );
            $xp = new XpProgress(
                $session->getScore(),
                $snapshot['xp'] + ($snapshot['processed'] ? 0 : $session->getScore()),
                pending: ! $snapshot['processed'],
            );
            $previousBest = $this->sessionRepository->findBestScoreBefore($session);
            $record = RecordState::resolve($session->getScore(), $previousBest);
            $canReplay = $this->sessionManager->countTodaySessions($user, $mode) < $this->sessionManager->maxDailySessions($user);
        }

        $pairing = $mode->resultLexicon() === ResultLexicon::PAIRING;
        $isPerfect = $session->getTotalQuestions() > 0 && $session->getCorrectAnswers() >= $session->getTotalQuestions();

        return new GameResultView(
            mode: $mode,
            difficulty: $session->getDifficulty(),
            correct: $session->getCorrectAnswers(),
            total: $session->getTotalQuestions(),
            accuracy: $session->getAccuracy(),
            score: $session->getScore(),
            durationSeconds: $session->getDurationSeconds(),
            dailyBonus: $session->isDailyBonus(),
            xp: $xp,
            previousBest: $previousBest,
            record: $record,
            answers: array_map(fn (GameQuestion $q): ResultAnswer => $this->answer($q, $cards), $questions),
            missed: $this->missed($questions, $cards, $pairing),
            nextStep: $canReplay ? $this->nextStep($session, $isPerfect && $mode->tracksAccuracy()) : null,
            canReplay: $canReplay,
        );
    }

    /**
     * @param list<GameQuestion> $questions
     * @return list<int>
     */
    private function spiceIds(array $questions): array
    {
        $ids = [];
        foreach ($questions as $question) {
            foreach (['questionSpiceId', 'correctSpiceId', 'givenSpiceId'] as $key) {
                $id = $this->intKey($question, $key);
                if ($id !== null) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param array<int, array{name: string, slug: ?string, active: bool}> $cards
     */
    private function answer(GameQuestion $question, array $cards): ResultAnswer
    {
        $data = $question->getQuestionData();
        $title = $this->nameFor($this->intKey($question, 'questionSpiceId'), $cards)
            ?? (\is_string($data['prompt'] ?? null) && $data['prompt'] !== '' ? $data['prompt'] : null);

        return new ResultAnswer(
            number: $question->getQuestionIndex() + 1,
            title: $title !== null ? $this->clip($title) : null,
            given: $this->givenName($question, $cards),
            expected: $question->isCorrect() ? null : $this->expectedName($question, $cards),
            correct: $question->isCorrect(),
        );
    }

    /**
     * @param list<GameQuestion>                                            $questions
     * @param array<int, array{name: string, slug: ?string, active: bool}> $cards
     * @return list<MissedSpice>
     */
    private function missed(array $questions, array $cards, bool $pairing): array
    {
        $missed = [];
        foreach ($questions as $question) {
            if ($question->isCorrect()) {
                continue;
            }

            $id = $this->intKey($question, 'correctSpiceId');
            $card = $id !== null ? ($cards[$id] ?? null) : null;
            if ($card === null || ! $card['active'] || $card['slug'] === null || isset($missed[$id])) {
                continue;
            }

            $missed[$id] = new MissedSpice(
                name: $card['name'],
                slug: $card['slug'],
                contextName: $pairing ? $this->nameFor($this->intKey($question, 'questionSpiceId'), $cards) : null,
                given: $this->givenName($question, $cards),
            );

            if (\count($missed) >= self::MAX_MISSED) {
                break;
            }
        }

        return array_values($missed);
    }

    private function nextStep(GameSession $session, bool $isPerfect): ?NextStep
    {
        $assessment = $this->difficultyAdvisor->adviseFor($session);
        if ($assessment instanceof SkillAssessment) {
            return new NextStep(
                $assessment->suggested,
                $assessment->isPromotion(),
                (int) round($assessment->winrate),
                $assessment->samples,
            );
        }

        $harder = $session->getDifficulty()
            ->harder();

        return $isPerfect && $harder !== null ? new NextStep($harder, true) : null;
    }

    /**
     * @param array<int, array{name: string, slug: ?string, active: bool}> $cards
     */
    private function givenName(GameQuestion $question, array $cards): ?string
    {
        $name = $this->nameFor($this->intKey($question, 'givenSpiceId'), $cards);
        if ($name !== null) {
            return $name;
        }

        $raw = trim((string) $question->getAnswerGiven());

        return $raw === '' || $raw === '—' ? null : $this->clip($raw);
    }

    /**
     * @param array<int, array{name: string, slug: ?string, active: bool}> $cards
     */
    private function expectedName(GameQuestion $question, array $cards): ?string
    {
        $name = $this->nameFor($this->intKey($question, 'correctSpiceId'), $cards);
        if ($name !== null) {
            return $name;
        }

        $raw = $question->getQuestionData()['correctAnswer'] ?? null;

        return \is_string($raw) && trim($raw) !== '' ? $this->clip($raw) : null;
    }

    /**
     * @param array<int, array{name: string, slug: ?string, active: bool}> $cards
     */
    private function nameFor(?int $id, array $cards): ?string
    {
        return $id !== null ? ($cards[$id]['name'] ?? null) : null;
    }

    private function intKey(GameQuestion $question, string $key): ?int
    {
        $value = $question->getQuestionData()[$key] ?? null;

        return \is_int($value) && $value > 0 ? $value : null;
    }

    private function clip(string $value): string
    {
        return u($value)->truncate(self::MAX_ANSWER_LENGTH, '…')->toString();
    }
}
