<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Achievement;
use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Contact;
use App\Entity\CookingTips;
use App\Entity\GameSession;
use App\Entity\PreparationMethods;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpicyType;
use App\Entity\Users;
use App\Service\Admin\AdminStatsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminStatsService $statsService,
        private readonly ChartBuilderInterface $chartBuilder,
    ) {
    }

    #[Route(path: '/admin', name: 'admin')]
    public function index(): Response
    {
        $userStats = $this->statsService->getUserStats();
        $gamificationStats = $this->statsService->getGamificationStats();
        $spiceStats = $this->statsService->getSpiceStats();
        $educationStats = $this->statsService->getEducationStats();
        $matchStats = $this->statsService->getMatchStats();

        // Level distribution chart
        $levelChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $levelLabels = array_map(
            fn (int $b) => "Niv. {$b}-" . ($b + 4),
            array_keys($gamificationStats['levelDistribution'])
        );
        $levelChart->setData([
            'labels' => $levelLabels ?: ['Aucun'],
            'datasets' => [
                [
                    'label' => 'Joueurs',
                    'backgroundColor' => '#f59e0b',
                    'data' => array_values($gamificationStats['levelDistribution']) ?: [0],
                ],
            ],
        ]);
        $levelChart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ]);

        // Top spices chart
        $spiceChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $spiceChart->setData([
            'labels' => array_column($spiceStats['topViewed'], 'name') ?: ['Aucun'],
            'datasets' => [
                [
                    'label' => 'Vues',
                    'backgroundColor' => '#ef4444',
                    'data' => array_column($spiceStats['topViewed'], 'views') ?: [0],
                ],
            ],
        ]);
        $spiceChart->setOptions([
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ]);

        // Activity timeline chart
        $activityChart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $activityChart->setData([
            'labels' => array_column($matchStats['recentActivity'], 'date') ?: ['—'],
            'datasets' => [
                [
                    'label' => 'Mélanges / jour',
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'data' => array_map('intval', array_column($matchStats['recentActivity'], 'count')) ?: [0],
                ],
            ],
        ]);
        $activityChart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);

        return $this->render('admin/dashboard.html.twig', [
            'userStats' => $userStats,
            'gamificationStats' => $gamificationStats,
            'spiceStats' => $spiceStats,
            'educationStats' => $educationStats,
            'matchStats' => $matchStats,
            'levelChart' => $levelChart,
            'spiceChart' => $spiceChart,
            'activityChart' => $activityChart,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('SpicyMatch');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-chart-line');

        yield MenuItem::section('Contenu');
        yield MenuItem::linkToCrud('Épices', 'fa fa-pepper-hot', Spices::class);
        yield MenuItem::linkToCrud('Groupes aromatiques', 'fa fa-layer-group', AromaticGroups::class);
        yield MenuItem::linkToCrud('Composants aromatiques', 'fa fa-atom', AromaticCompound::class);
        yield MenuItem::linkToCrud('Alchimie des saveurs', 'fa fa-flask', AlchemyFlavors::class);
        yield MenuItem::linkToCrud('Types d\'épices', 'fa fa-tag', SpicyType::class);

        yield MenuItem::section('Préparation');
        yield MenuItem::linkToCrud('Conseils de cuisson', 'fa fa-fire', CookingTips::class);
        yield MenuItem::linkToCrud('Conseils de préparation', 'fa fa-mortar-pestle', PreparationTips::class);
        yield MenuItem::linkToCrud('Méthodes de préparation', 'fa fa-list-check', PreparationMethods::class);

        yield MenuItem::section('Gamification');
        yield MenuItem::linkToCrud('Succès', 'fa fa-trophy', Achievement::class);
        yield MenuItem::linkToCrud('Sessions de jeu', 'fa fa-gamepad', GameSession::class);

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', Users::class);
        yield MenuItem::linkToCrud('Contact', 'fa fa-envelope', Contact::class);

        yield MenuItem::section('');
        yield MenuItem::linkToRoute('Site SpicyMatch', 'fa fa-external-link', 'home');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDefaultSort([
                'created_at' => 'DESC',
            ])
            ->setPaginatorPageSize(30);
    }
}
