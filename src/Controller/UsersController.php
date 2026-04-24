<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserAchievement;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Form\UsersMailType;
use App\Repository\AchievementProgressRepository;
use App\Repository\AchievementRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users')]
#[IsGranted('ROLE_USER')]
class UsersController extends AbstractController
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly AchievementRepository $achievementRepository,
        private readonly UserAchievementRepository $userAchievementRepository,
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly SpiceViewRepository $spiceViewRepository,
        private readonly AchievementProgressRepository $achievementProgressRepository,
    ) {
    }

    #[Route('/', name: 'dashboard_user', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        return $this->render('users/dashboard.html.twig', [
            'progression' => $user->getProgression(),
        ]);
    }

    /**
     * RGPD article 20 — data portability.
     * Returns a JSON download of everything we hold on the current user.
     */
    #[Route('/export', name: 'export_user_data', methods: ['GET'])]
    public function exportData(): JsonResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $progression = $user->getProgression();
        $stats = $user->getStats();

        $payload = [
            'exportedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'account' => [
                'username' => $user->getUsername(),
                'email' => $user->getMail(),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()?->format(\DATE_ATOM),
                'lastLoginAt' => $user->getLastLoginAt()?->format(\DATE_ATOM),
            ],
            'progression' => $progression === null ? null : [
                'xp' => $progression->getXp(),
                'level' => $progression->getLevel(),
                'totalMatches' => $progression->getTotalMatches(),
                'uniqueSpicesUsed' => $progression->getUniqueSpicesUsed(),
                'totalSpicesRead' => $progression->getTotalSpicesRead(),
                'currentReadingStreak' => $progression->getCurrentReadingStreak(),
                'longestReadingStreak' => $progression->getLongestReadingStreak(),
                'discoveries' => $progression->getDiscoveries(),
                'gamificationEnabled' => $progression->isGamificationEnabled(),
            ],
            'stats' => $stats === null ? null : [
                'easterEggsFound' => $stats->getEasterEggsFound(),
                'foundEggSlugs' => $stats->getFoundEggSlugs(),
                'visitedAromaticGroups' => $stats->getVisitedAromaticGroups(),
                'totalActions' => $stats->totalActions,
            ],
            'achievements' => array_map(
                static fn ($ua) => [
                    'slug' => $ua->getAchievement()?->getSlug(),
                    'name' => $ua->getAchievement()?->getName(),
                    'unlockedAt' => $ua->getUnlockedAt()
                        ->format(\DATE_ATOM),
                ],
                $progression !== null
                    ? $this->userAchievementRepository->findByProgressionWithAchievement($progression)
                    : [],
            ),
            'matchHistory' => array_map(
                static fn ($h) => [
                    'id' => $h->getId(),
                    'title' => $h->getTitle(),
                    'favorite' => $h->isFavorite(),
                    'createdAt' => $h->getCreatedAt()
                        ->format(\DATE_ATOM),
                ],
                $this->historyRepository->findBy([
                    'user' => $user,
                ]),
            ),
            'spicesRead' => array_map(
                static fn ($sv) => [
                    'spiceId' => $sv->getSpice()?->getId(),
                    'spiceName' => $sv->getSpice()?->getName(),
                    'viewedDay' => $sv->getViewedDay()
                        ->format('Y-m-d'),
                ],
                $this->spiceViewRepository->findBy([
                    'user' => $user,
                ]),
            ),
        ];

        $response = new JsonResponse($payload);
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="spicymatch-export-%s.json"', $user->getUsername()),
        );

        return $response;
    }

    #[Route('/history', name: 'history_user', methods: ['GET'])]
    public function history(Request $request, PaginatorInterface $paginator): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $pagination = $paginator->paginate(
            $this->historyRepository->findByUserQuery($user),
            $request->query->getInt('page', 1),
            10,
        );

        return $this->render('users/history.html.twig', [
            'user' => 'history',
            'pagination' => $pagination,
        ]);
    }

    #[Route('/security', name: 'security_user', methods: ['GET'])]
    public function security(): Response
    {
        return $this->render('users/security.html.twig', [
            'user' => 'security',
        ]);
    }

    #[Route('/configuration', name: 'configuration_user', methods: ['GET'])]
    public function configuration(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        return $this->render('users/configuration.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/userMail', name: 'mail_user', methods: ['GET', 'POST'])]
    public function userMail(Request $request): Response
    {
        $user = $this->getUser();

        $formMail = $this->createForm(UsersMailType::class, $user);
        $formMail->handleRequest($request);

        if ($formMail->isSubmitted() && $formMail->isValid()) {
            $user = $formMail->getData();

            $this->usersRepository->addOrUpdate($user);

            return $this->redirectToRoute('configuration_user');
        }

        return $this->render('users/_form_mail.html.twig', [
            'user' => $user,
            'form' => $formMail,
        ]);
    }

    #[Route('/profile', name: 'profile_user', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $histories = $this->historyRepository->findByUser($user);

        $stats = [
            'totalBlends' => count($histories),
            'distinctSpices' => $this->historyRepository->countDistinctSpicesByUser($user),
            'favorites' => $this->historyRepository->countFavoritesByUser($user),
            'spicesViewed' => $this->spiceViewRepository->countDistinctSpicesByUser($user),
        ];

        return $this->render('users/profile.html.twig', [
            'progression' => $user->getProgression(),
            'userStats' => $user->getStats(),
            'latestHistories' => array_slice($histories, 0, 3),
            'stats' => $stats,
        ]);
    }

    #[Route('/favorites', name: 'favorites_user', methods: ['GET'])]
    public function favorites(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        return $this->render('users/favorites.html.twig', [
            'favorites' => $this->historyRepository->findFavoritesByUser($user),
        ]);
    }

    #[Route('/achievements', name: 'achievements_user', methods: ['GET'])]
    public function achievements(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();
        $progression = $user->getProgression();

        // Index AchievementProgress rows by achievement id — avoids N+1 lookups in Twig.
        $progressByAchievementId = [];
        foreach ($this->achievementProgressRepository->findByUser($user) as $ap) {
            $achievement = $ap->getAchievement();
            if ($achievement !== null && $achievement->getId() !== null) {
                $progressByAchievementId[$achievement->getId()] = $ap;
            }
        }

        return $this->render('users/achievements.html.twig', [
            'progression' => $progression,
            'allAchievements' => $this->achievementRepository->findAllOrdered(),
            'userAchievements' => $progression
                ? $this->userAchievementRepository->findByProgressionWithAchievement($progression)
                : [],
            'progressByAchievementId' => $progressByAchievementId,
        ]);
    }

    #[Route('/gamification/toggle', name: 'toggle_gamification_user', methods: ['POST'])]
    public function toggleGamification(Request $request, EntityManagerInterface $em): Response
    {
        if (! $this->isCsrfTokenValid('toggle_gamification', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('configuration_user');
        }

        /** @var Users $user */
        $user = $this->getUser();
        $progression = $user->getProgression();

        if ($progression !== null) {
            $progression->isGamificationEnabled()
                ? $progression->disableGamification()
                : $progression->enableGamification();
            $em->flush();
        }

        // Server-side counter for the "alchimiste_de_l_ombre" easter egg
        // (toggle the gamification switch 5 times in a row). Lives in session
        // — never trusted from client payload.
        $session = $request->getSession();
        $count = (int) $session->get('easter_egg.alchimiste_count', 0);
        $session->set('easter_egg.alchimiste_count', $count + 1);

        return $this->redirectToRoute('configuration_user');
    }

    #[Route('/difficulty/update', name: 'update_difficulty_user', methods: ['POST'])]
    public function updateDifficulty(Request $request, EntityManagerInterface $em): Response
    {
        if (! $this->isCsrfTokenValid('update_difficulty', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('configuration_user');
        }

        $difficulty = GameDifficulty::tryFrom($request->request->getString('difficulty'));

        if ($difficulty === null) {
            $this->addFlash('error', 'Difficulté invalide.');

            return $this->redirectToRoute('configuration_user');
        }

        /** @var Users $user */
        $user = $this->getUser();
        $user->setPreferredDifficulty($difficulty);
        $em->flush();

        $this->addFlash('success', 'Posture mise à jour.');

        return $this->redirectToRoute('configuration_user');
    }

    #[Route('/badge/equip/{id}', name: 'equip_badge_user', methods: ['POST'])]
    public function equipBadge(Request $request, UserAchievement $ua, EntityManagerInterface $em): Response
    {
        /** @var Users $user */
        $user = $this->getUser();
        $progression = $user->getProgression();

        if (! $this->isCsrfTokenValid('equip_badge_' . $ua->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');

            return $this->redirectToRoute('achievements_user');
        }

        if ($progression === null || $ua->getUserProgression() !== $progression) {
            throw $this->createAccessDeniedException();
        }

        $progression->equipBadge($ua);
        $em->flush();

        return $this->redirectToRoute('achievements_user');
    }

    #[Route('/{id}', name: 'delete_user', methods: ['POST'])]
    public function delete(Request $request, Users $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $user->setDeletedAt(new \DateTimeImmutable());
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_logout', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('dashboard_user', [], Response::HTTP_SEE_OTHER);
    }
}
