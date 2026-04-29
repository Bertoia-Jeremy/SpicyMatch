<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\AdminStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/gamification')]
final class GamificationStatsController extends AbstractController
{
    public function __construct(
        private readonly AdminStatsService $stats,
    ) {
    }

    #[Route('/stats', name: 'admin_gamification_stats', methods: ['GET'])]
    public function stats(): Response
    {
        return $this->render('admin/gamification_stats.html.twig', [
            'unlockRates' => $this->stats->achievementUnlockRate(),
            'sessionsPerMode' => $this->stats->sessionsPerModePerDay(30),
            'xpPerDay' => $this->stats->xpPerDay(30),
            'anomalies' => $this->stats->anomalies(10),
        ]);
    }
}
