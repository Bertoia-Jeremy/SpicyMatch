<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use App\Service\Education\GameSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/education')]
class EducationController extends AbstractController
{
    public function __construct(
        private readonly GameSessionManager $sessionManager,
        private readonly GameSessionRepository $sessionRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'education_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        return $this->render('education/index.html.twig', [
            'modes' => array_filter(GameMode::cases(), fn (GameMode $m) => $m->isEnabled()),
            'difficulties' => GameDifficulty::cases(),
            'recentSessions' => $this->sessionRepository->findByUser($user, 5),
        ]);
    }

    #[Route('/start', name: 'education_start', methods: ['POST'])]
    public function start(Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $mode = GameMode::tryFrom($request->request->getString('mode')) ?? GameMode::QCM;
        $difficulty = GameDifficulty::tryFrom($request->request->getString('difficulty')) ?? GameDifficulty::EASY;

        if (! $this->isCsrfTokenValid('education_start', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('education_index');
        }

        try {
            $session = $this->sessionManager->startSession($user, $mode, $difficulty);
        } catch (\RuntimeException $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToRoute('education_index');
        }

        return $this->redirectToRoute('education_play', [
            'id' => $session->getId(),
        ]);
    }

    #[Route('/play/{id}', name: 'education_play', methods: ['GET'])]
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

        // Persist question data in session store for answer validation
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

        // Retrieve stored question
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

        // Store full question data in GameQuestion
        $questionData = $storedQuestion;

        $result = $this->sessionManager->answerQuestion($session, $answer, $correctAnswer, $timeSpentMs);

        // Update question data with full context
        $lastQuestion = $session->getQuestions()
            ->last();
        if ($lastQuestion !== false) {
            $lastQuestion->setQuestionData($questionData);
            $this->em->flush();
        }

        // Clear stored question
        $request->getSession()
            ->remove('current_question_' . $id);

        if ($result['finished']) {
            return $this->redirectToRoute('education_result', [
                'id' => $session->getId(),
            ]);
        }

        // Show feedback briefly then redirect to next question
        return $this->render('education/feedback.html.twig', [
            'session' => $session,
            'correct' => $result['correct'],
            'answer' => $answer,
            'correctAnswer' => $correctAnswer,
            'question' => $storedQuestion,
        ]);
    }

    #[Route('/play-live/{mode}', name: 'education_play_live', methods: ['GET'])]
    public function playLive(string $mode, Request $request): Response
    {
        $gameMode = GameMode::tryFrom($mode);

        if ($gameMode === null || ! $gameMode->isLiveComponent()) {
            throw $this->createNotFoundException();
        }

        $difficulty = GameDifficulty::tryFrom($request->query->getString('difficulty')) ?? GameDifficulty::EASY;

        /** @var Users $user */
        $user = $this->getUser();

        $todayCount = $this->sessionRepository->countTodayByUser($user, $gameMode);

        if ($todayCount >= 5) {
            $this->addFlash('warning', sprintf(
                'Limite quotidienne atteinte (5 sessions %s par jour).',
                $gameMode->label(),
            ));

            return $this->redirectToRoute('education_index');
        }

        return $this->render('education/play_live.html.twig', [
            'mode' => $gameMode,
            'difficulty' => $difficulty,
        ]);
    }

    #[Route('/result/{id}', name: 'education_result', methods: ['GET'])]
    public function result(int $id): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $session = $this->sessionRepository->find($id);
        if ($session === null || $session->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('education/result.html.twig', [
            'session' => $session,
        ]);
    }
}
