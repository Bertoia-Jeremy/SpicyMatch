<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Repository\AchievementProgressRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly AchievementProgressRepository $achievementProgressRepository,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        /** @var Users|null $user */
        $user = $this->getUser();

        if ($user && $user->getLastLoginAt()) {
            $today = new \DateTimeImmutable();
            $lastLogin = $user->getLastLoginAt();

            if ($lastLogin->format('Y-m-d') < $today->format('Y-m-d')) {
                $this->addFlash('info', 'Bienvenue de retour, ' . $user->getUserIdentifier() . '!');
            }
        } elseif ($user) {
            $this->addFlash('info', 'Bienvenue, ' . $user->getUserIdentifier() . '!');
        }

        $nextAchievementProgress = null;
        if ($user !== null) {
            $nextAchievementProgress = $this->achievementProgressRepository->findMostAdvancedNotCompleted($user);
        }

        return $this->render('home/index.html.twig', [
            'nextAchievementProgress' => $nextAchievementProgress,
        ]);
    }
}
