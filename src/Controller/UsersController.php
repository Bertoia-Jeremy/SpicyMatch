<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserAchievement;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Form\ChangePasswordType;
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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/users', defaults: [
    '_locale' => 'fr',
])]
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
        private readonly TranslatorInterface $translator,
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

    #[Route('/security', name: 'security_user', methods: ['GET'])]
    public function security(): Response
    {
        return $this->render('users/security.html.twig', [
            'user' => 'security',
        ]);
    }

    #[Route('/userMail', name: 'mail_user', methods: ['GET', 'POST'])]
    public function userMail(Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $formMail = $this->createForm(UsersMailType::class, $user, [
            'action' => $this->generateUrl('mail_user'),
        ]);
        $formMail->handleRequest($request);

        if ($formMail->isSubmitted() && $formMail->isValid()) {
            $this->usersRepository->addOrUpdate($user);

            return $this->redirectToRoute('profile_tab', [
                'tab' => 'lab',
            ]);
        }

        return $this->renderLabFragment($user, 'mail', $formMail);
    }

    #[Route('/password/change', name: 'change_password_user', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        /** @var Users $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class, null, [
            'action' => $this->generateUrl('change_password_user'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $em->flush();
            $this->addFlash('success', $this->translator->trans('ui.security.password_changed'));

            return $this->redirectToRoute('profile_tab', [
                'tab' => 'lab',
            ]);
        }

        return $this->renderLabFragment($user, 'password', null, $form);
    }

    private function renderLabFragment(
        Users $user,
        ?string $edit,
        ?FormInterface $mailForm = null,
        ?FormInterface $passwordForm = null,
    ): Response {
        $edit = in_array($edit, ['mail', 'password'], true) ? $edit : null;

        if ($edit === 'mail' && $mailForm === null) {
            $mailForm = $this->createForm(UsersMailType::class, $user, [
                'action' => $this->generateUrl('mail_user'),
            ]);
        }

        if ($edit === 'password' && $passwordForm === null) {
            $passwordForm = $this->createForm(ChangePasswordType::class, null, [
                'action' => $this->generateUrl('change_password_user'),
            ]);
        }

        return $this->render('users/tabs/_lab.html.twig', [
            'user' => $user,
            'editSection' => $edit,
            'mailForm' => $mailForm?->createView(),
            'passwordForm' => $passwordForm?->createView(),
        ]);
    }

    #[Route('/profile', name: 'profile_user', methods: ['GET'])]
    public function profile(Request $request): Response
    {
        $tab = $request->query->getString('tab', 'dashboard');
        if (! in_array($tab, ['dashboard', 'grimoire', 'history', 'lab'], true)) {
            $tab = 'dashboard';
        }

        return $this->render('users/profile.html.twig', [
            'activeTab' => $tab,
        ]);
    }

    #[Route('/profile/tab/{tab}', name: 'profile_tab', methods: ['GET'], requirements: [
        'tab' => 'dashboard|grimoire|history|lab',
    ])]
    public function profileTab(string $tab, Request $request, PaginatorInterface $paginator): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        if ($tab === 'lab') {
            return $this->renderLabFragment($user, $request->query->getString('edit') ?: null);
        }

        $data = match ($tab) {
            'dashboard' => [
                'progression' => $user->getProgression(),
                'userStats' => $user->getStats(),
                'latestHistories' => $this->historyRepository->findByUserWithLimit($user, 3),
                'stats' => [
                    'totalBlends' => $this->historyRepository->countByUser($user),
                    'distinctSpices' => $this->historyRepository->countDistinctSpicesByUser($user),
                    'favorites' => $this->historyRepository->countFavoritesByUser($user),
                    'spicesViewed' => $this->spiceViewRepository->countDistinctSpicesByUser($user),
                ],
            ],
            'grimoire' => $this->grimoireData($user),
            'history' => $this->historyData($user, $request, $paginator),
            default => throw $this->createNotFoundException(),
        };

        return $this->render('users/tabs/_' . $tab . '.html.twig', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function grimoireData(Users $user): array
    {
        $progression = $user->getProgression();

        $progressByAchievementId = [];
        foreach ($this->achievementProgressRepository->findByUser($user) as $ap) {
            $achievement = $ap->getAchievement();
            if ($achievement !== null && $achievement->getId() !== null) {
                $progressByAchievementId[$achievement->getId()] = $ap;
            }
        }

        return [
            'progression' => $progression,
            'allAchievements' => $this->achievementRepository->findAllOrdered(),
            'userAchievements' => $progression
                ? $this->userAchievementRepository->findByProgressionWithAchievement($progression)
                : [],
            'progressByAchievementId' => $progressByAchievementId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyData(Users $user, Request $request, PaginatorInterface $paginator): array
    {
        $filter = $request->query->getString('filter', 'all');
        if (! in_array($filter, ['all', 'favorites', 'manual'], true)) {
            $filter = 'all';
        }

        $query = match ($filter) {
            'favorites' => $this->historyRepository->findFavoritesByUserQuery($user),
            'manual' => $this->historyRepository->findManualByUserQuery($user),
            default => $this->historyRepository->findByUserQuery($user),
        };

        return [
            'pagination' => $paginator->paginate($query, $request->query->getInt('page', 1), 10),
            'currentFilter' => $filter,
        ];
    }

    #[Route('/gamification/toggle', name: 'toggle_gamification_user', methods: ['POST'])]
    public function toggleGamification(Request $request, EntityManagerInterface $em): Response
    {
        if (! $this->isCsrfTokenValid('toggle_gamification', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.token_invalid'));

            return $this->redirectToRoute('profile_user', [
                'tab' => 'lab',
            ]);
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

        $session = $request->getSession();
        $count = (int) $session->get('easter_egg.alchimiste_count', 0);
        $session->set('easter_egg.alchimiste_count', $count + 1);

        return $this->redirectToRoute('profile_tab', [
            'tab' => 'lab',
        ]);
    }

    #[Route('/difficulty/update', name: 'update_difficulty_user', methods: ['POST'])]
    public function updateDifficulty(Request $request, EntityManagerInterface $em): Response
    {
        if (! $this->isCsrfTokenValid('update_difficulty', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.token_invalid'));

            return $this->redirectToRoute('profile_user', [
                'tab' => 'lab',
            ]);
        }

        $difficulty = GameDifficulty::tryFrom($request->request->getString('difficulty'));

        if ($difficulty === null) {
            $this->addFlash('error', $this->translator->trans('flash.difficulty_invalid'));

            return $this->redirectToRoute('profile_user', [
                'tab' => 'lab',
            ]);
        }

        /** @var Users $user */
        $user = $this->getUser();
        $user->setPreferredDifficulty($difficulty);
        $em->flush();

        $this->addFlash('success', $this->translator->trans('flash.posture_updated'));

        return $this->redirectToRoute('profile_tab', [
            'tab' => 'lab',
        ]);
    }

    #[Route('/badge/equip/{id}', name: 'equip_badge_user', methods: ['POST'])]
    public function equipBadge(Request $request, UserAchievement $ua, EntityManagerInterface $em): Response
    {
        /** @var Users $user */
        $user = $this->getUser();
        $progression = $user->getProgression();

        if (! $this->isCsrfTokenValid('equip_badge_' . $ua->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.token_invalid'));

            return $this->redirectToRoute('profile_user', [
                'tab' => 'grimoire',
            ]);
        }

        if ($progression === null || $ua->getUserProgression() !== $progression) {
            throw $this->createAccessDeniedException();
        }

        $progression->equipBadge($ua);
        $em->flush();

        return $this->redirectToRoute('profile_user', [
            'tab' => 'grimoire',
        ]);
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
