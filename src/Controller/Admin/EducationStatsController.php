<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\AdminStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/education')]
final class EducationStatsController extends AbstractController
{
    public function __construct(
        private readonly AdminStatsService $stats,
    ) {
    }

    #[Route('/stats', name: 'admin_education_stats', methods: ['GET'])]
    public function stats(): Response
    {
        return $this->render('admin/education_stats.html.twig', [
            'breakdown' => $this->stats->getEducationStatsBreakdown(),
        ]);
    }
}
