<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Enum\GameMode;
use App\Repository\AchievementProgressRepository;
use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use App\Service\Education\DailyChallengeResolver;
use App\Service\Education\GameSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}', defaults: [
    '_locale' => 'fr',
])]
class HomeController extends AbstractController
{
    public const array DEMO_SPICE_SLUGS = ['cumin', 'cannelle', 'curcuma', 'gingembre', 'cardamome', 'coriandre'];

    public function __construct(
        private readonly AchievementProgressRepository $achievementProgressRepository,
        private readonly SpicesRepository $spicesRepository,
        private readonly AromaticCompoundRepository $aromaticCompoundRepository,
        private readonly DailyChallengeResolver $dailyChallenge,
        private readonly GameSessionManager $gameSessionManager,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(Request $request, TranslatorInterface $translator, #[CurrentUser] ?Users $user = null): Response
    {
        if ($user instanceof Users && $user->getLastLoginAt() instanceof \DateTimeInterface) {
            $today = new \DateTimeImmutable();
            $lastLogin = $user->getLastLoginAt();

            if ($lastLogin->format('Y-m-d') < $today->format('Y-m-d')) {
                $this->addFlash('info', $translator->trans('flash.welcome_back', [
                    '%username%' => $user->getUserIdentifier(),
                ]));
            }
        } elseif ($user instanceof Users) {
            $this->addFlash('info', $translator->trans('flash.welcome', [
                '%username%' => $user->getUserIdentifier(),
            ]));
        }

        $nextAchievementProgress = null;
        if ($user instanceof Users) {
            $nextAchievementProgress = $this->achievementProgressRepository->findMostAdvancedNotCompleted($user);
        }

        $gameModes = array_filter(GameMode::cases(), fn (GameMode $m): bool => $m->isEnabled());

        return $this->render('home/index.html.twig', [
            'nextAchievementProgress' => $nextAchievementProgress,
            'spicesCount' => $this->spicesRepository->countTotal(),
            'compoundsCount' => $this->aromaticCompoundRepository->countTotal(),
            'demoSpices' => $this->spicesRepository->findDemoCards(self::DEMO_SPICE_SLUGS, $request->getLocale()),
            'gameModes' => $gameModes,
            'dailyFeaturedMode' => $this->dailyChallenge->forUser($user),
            'dailyBonusAvailable' => $this->gameSessionManager->isDailyBonusAvailable($user),
            'dailyGamesFree' => GameSessionManager::MAX_DAILY_SESSIONS_FREE,
            'dailyGamesPremium' => GameSessionManager::MAX_DAILY_SESSIONS_PREMIUM,
        ]);
    }
}
