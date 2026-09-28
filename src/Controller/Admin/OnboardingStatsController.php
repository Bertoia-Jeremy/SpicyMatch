<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\AdminStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/onboarding')]
final class OnboardingStatsController extends AbstractController
{
    public function __construct(
        private readonly AdminStatsService $stats,
    ) {
    }

    #[Route('/stats', name: 'admin_onboarding_stats', methods: ['GET'])]
    public function stats(): Response
    {
        return $this->render('admin/onboarding_stats.html.twig', [
            'completionByStep' => $this->stats->onboardingCompletionByStep(),
        ]);
    }
}
