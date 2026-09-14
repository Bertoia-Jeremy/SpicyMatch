<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\AchievementRepository;
use App\Repository\GameSessionRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\DifficultyAdvisor;
use App\Service\Education\GameSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/education', defaults: [
    '_locale' => 'fr',
])]
class EducationController extends AbstractController
{
    public function __construct(
        private readonly GameSessionManager $sessionManager,
        private readonly GameSessionRepository $sessionRepository,
        private readonly AcademyManager $academyManager,
        private readonly DifficultyAdvisor $difficultyAdvisor,
        private readonly AchievementRepository $achievementRepository,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/', name: 'education_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users|null $user */
        $user = $this->getUser();

        $modes = array_filter(GameMode::cases(), fn (GameMode $m) => $m->isEnabled());

        $dailyCounts = [];
        foreach ($modes as $mode) {
            $dailyCounts[$mode->value] = 0;
        }
        $recentSessions = [];
        $bestScores = [];
        $userDifficulty = GameDifficulty::EASY->value;
        $progression = null;
        $gamesPlayed = 0;
        $achievementsUnlocked = 0;
        $unlockedByMode = [];
        foreach ($modes as $mode) {
            $unlockedByMode[$mode->value] = true;
        }

        if ($user !== null) {
            $grouped = $this->sessionRepository->countTodayByUserGrouped($user);
            foreach ($modes as $mode) {
                $dailyCounts[$mode->value] = $grouped[$mode->value] ?? 0;
            }
            $bestScores = $this->sessionRepository->findBestScoreByUserGrouped($user);
            $recentSessions = $this->sessionRepository->findByUser($user, 5);
            $userDifficulty = $user->getPreferredDifficulty()
                ->value;
            $progression = $user->getProgression();
            $gamesPlayed = $this->sessionRepository->countFinishedByUser($user);
            $achievementsUnlocked = $progression?->getUserAchievements()
                ->count() ?? 0;
            $userLevel = $progression?->getLevel() ?? 1;
            foreach ($modes as $mode) {
                $unlockedByMode[$mode->value] = $mode->isUnlockedForLevel($userLevel);
            }
        }

        $dailyFeaturedMode = GameMode::dailyFeatured($modes);

        return $this->render('education/index.html.twig', [
            'modes' => $modes,
            'difficulties' => GameDifficulty::cases(),
            'recentSessions' => $recentSessions,
            'dailyCounts' => $dailyCounts,
            'bestScores' => $bestScores,
            'maxDailySessions' => $this->sessionManager->maxDailySessions($user),
            'userDifficulty' => $userDifficulty,
            'progression' => $progression,
            'unlockedByMode' => $unlockedByMode,
            'gamesPlayed' => $gamesPlayed,
            'achievementsUnlocked' => $achievementsUnlocked,
            'achievementsTotal' => $this->achievementRepository->count([
                'enabled' => true,
            ]),
            'dailyFeaturedMode' => $dailyFeaturedMode,
        ]);
    }

    #[Route('/briefing', name: 'education_briefing', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function briefing(Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $mode = GameMode::tryFrom($request->query->getString('mode')) ?? GameMode::QCM;
        $difficulty = GameDifficulty::tryFrom($request->query->getString('difficulty')) ?? GameDifficulty::EASY;

        return $this->render('education/briefing.html.twig', [
            'mode' => $mode,
            'difficulty' => $difficulty,
            'difficulties' => GameDifficulty::cases(),
            'rules' => $this->academyManager->getRulesFor($mode),
        ]);
    }

    #[Route('/start', name: 'education_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function start(Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $mode = GameMode::tryFrom($request->request->getString('mode')) ?? GameMode::QCM;
        $difficulty = GameDifficulty::tryFrom($request->request->getString('difficulty')) ?? GameDifficulty::EASY;

        if (! $this->isCsrfTokenValid('education_start', $request->request->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.csrf_invalid'));

            return $this->redirectToRoute('education_index');
        }

        if (! $mode->isUnlockedForLevel($user->getProgression()?->getLevel() ?? 1)) {
            $this->addFlash('warning', $this->translator->trans('ui.edu.locked_level_hint', [
                '%mode%' => $this->translator->trans($mode->label()),
                '%level%' => $mode->requiredLevel(),
            ]));

            return $this->redirectToRoute('education_index');
        }

        if ($mode !== GameMode::QCM) {
            return $this->redirectToRoute('education_play_live', [
                'mode' => $mode->value,
                'difficulty' => $difficulty->value,
            ]);
        }

        try {
            $session = $this->sessionManager->startSession($user, $mode, $difficulty);
        } catch (\RuntimeException) {
            $this->addFlash('warning', $this->translator->trans('flash.daily_limit_reached', [
                '%mode%' => $this->translator->trans($mode->label()),
            ]));

            return $this->redirectToRoute('education_index');
        }

        return $this->redirectToRoute('education_play', [
            'id' => $session->getId(),
        ]);
    }

    #[Route('/play/{id}', name: 'education_play', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function play(int $id): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $session = $this->sessionRepository->find($id);
        if ($session === null || $session->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        if ($session->isFinished()) {
            return $this->redirectToRoute('education_result', [
                'id' => $session->getId(),
            ]);
        }

        $question = $this->sessionManager->nextQuestion($session);
        if ($question === null) {
            return $this->redirectToRoute('education_result', [
                'id' => $session->getId(),
            ]);
        }

        $request = $this->container->get('request_stack')
            ->getCurrentRequest();
        $request->getSession()
            ->set('current_question_' . $id, $question);

        return $this->render('education/play.html.twig', [
            'session' => $session,
            'question' => $question,
            'questionNumber' => $session->getCurrentQuestionIndex() + 1,
        ]);
    }

    #[Route('/answer/{id}', name: 'education_answer', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function answer(int $id, Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $session = $this->sessionRepository->find($id);
        if ($session === null || $session->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        if ($session->isFinished()) {
            return $this->redirectToRoute('education_result', [
                'id' => $session->getId(),
            ]);
        }

        $storedQuestion = $request->getSession()
            ->get('current_question_' . $id);
        if ($storedQuestion === null) {
            return $this->redirectToRoute('education_play', [
                'id' => $id,
            ]);
        }

        if (! $this->isCsrfTokenValid('education_answer', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('education_play', [
                'id' => $id,
            ]);
        }

        $answer = $request->request->getString('answer');
        $correctAnswer = $storedQuestion['correctAnswer'];
        $timeSpentMs = $request->request->getInt('timeSpentMs') ?: null;

        $questionData = $storedQuestion;

        $result = $this->sessionManager->answerQuestion($session, $answer, $correctAnswer, $timeSpentMs);

        $lastQuestion = $session->getQuestions()
            ->last();
        if ($lastQuestion !== false) {
            $lastQuestion->setQuestionData($questionData);
            $this->em->flush();
        }

        $request->getSession()
            ->remove('current_question_' . $id);

        if ($result['finished']) {
            return $this->redirectToRoute('education_result', [
                'id' => $session->getId(),
            ]);
        }

        return $this->render('education/play.html.twig', [
            'session' => $session,
            'question' => $storedQuestion,
            'questionNumber' => $session->getCurrentQuestionIndex(),
            'showFeedback' => true,
            'isCorrect' => $result['correct'],
            'selectedAnswer' => $answer,
            'correctAnswer' => $correctAnswer,
        ]);
    }

    #[Route('/play-live/{mode}', name: 'education_play_live', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function playLive(string $mode, Request $request): Response
    {
        $gameMode = GameMode::tryFrom($mode);

        if ($gameMode === null || ! $gameMode->isLiveComponent()) {
            throw $this->createNotFoundException();
        }

        $difficulty = GameDifficulty::tryFrom($request->query->getString('difficulty')) ?? GameDifficulty::EASY;

        /** @var Users $user */
        $user = $this->getUser();

        if (! $gameMode->isUnlockedForLevel($user->getProgression()?->getLevel() ?? 1)) {
            $this->addFlash('warning', $this->translator->trans('ui.edu.locked_level_hint', [
                '%mode%' => $this->translator->trans($gameMode->label()),
                '%level%' => $gameMode->requiredLevel(),
            ]));

            return $this->redirectToRoute('education_index');
        }

        $todayCount = $this->sessionRepository->countTodayByUser($user, $gameMode);

        if ($todayCount >= $this->sessionManager->maxDailySessions($user)) {
            $this->addFlash('warning', $this->translator->trans('flash.daily_limit_reached', [
                '%mode%' => $this->translator->trans($gameMode->label()),
                '%max%' => $this->sessionManager->maxDailySessions($user),
            ]));

            return $this->redirectToRoute('education_index');
        }

        return $this->render('education/play_live.html.twig', [
            'mode' => $gameMode,
            'difficulty' => $difficulty,
        ]);
    }

    #[Route('/result/{id}', name: 'education_result', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function result(int $id): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $session = $this->sessionRepository->find($id);
        if ($session === null || $session->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        $todayCount = $this->sessionRepository->countTodayByUser($user, $session->getGameMode());

        return $this->render('education/result.html.twig', [
            'session' => $session,
            'canReplay' => $todayCount < $this->sessionManager->maxDailySessions($user),
            'skillAssessment' => $this->difficultyAdvisor->adviseFor($session),
        ]);
    }
}
