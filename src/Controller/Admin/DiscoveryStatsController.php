<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\AdminStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/discovery')]
final class DiscoveryStatsController extends AbstractController
{
    public function __construct(
        private readonly AdminStatsService $stats,
    ) {
    }

    #[Route('/stats', name: 'admin_discovery_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $spiceStats = $this->stats->getSpiceStats();

        return $this->render('admin/discovery_stats.html.twig', [
            'topViewed' => $spiceStats['topViewed'],
            'groupPopularity' => $spiceStats['groupPopularity'],
            'readingStreaks' => $this->stats->activeReadingStreaks(),
        ]);
    }
}
