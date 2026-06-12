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
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class DashboardController extends AbstractDashboardController
{
    /**
     * Domaine de traduction du back-office.
     */
    private const DOMAIN = 'admin';

    public function __construct(
        private readonly AdminStatsService $statsService,
        private readonly ChartBuilderInterface $chartBuilder,
        private readonly TranslatorInterface $translator,
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
            fn (int $b) => $this->translator->trans('admin.chart.level_bucket', [
                '%from%' => $b,
                '%to%' => $b + 4,
            ], self::DOMAIN),
            array_keys($gamificationStats['levelDistribution'])
        );
        $noneLabel = $this->translator->trans('admin.chart.none', [], self::DOMAIN);
        $levelChart->setData([
            'labels' => $levelLabels ?: [$noneLabel],
            'datasets' => [
                [
                    'label' => $this->translator->trans('admin.chart.players', [], self::DOMAIN),
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
            'labels' => array_column($spiceStats['topViewed'], 'name') ?: [$noneLabel],
            'datasets' => [
                [
                    'label' => $this->translator->trans('admin.chart.views', [], self::DOMAIN),
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
                    'label' => $this->translator->trans('admin.chart.matches_per_day', [], self::DOMAIN),
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
            ->setTitle('SpicyMatch')
            ->setTranslationDomain(self::DOMAIN);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('admin.dashboard.menu', 'fa fa-chart-line');

        yield MenuItem::section('admin.menu.section_content');
        yield MenuItem::linkToCrud('admin.menu.spices', 'fa fa-pepper-hot', Spices::class);
        yield MenuItem::linkToCrud('admin.menu.aromatic_groups', 'fa fa-layer-group', AromaticGroups::class);
        yield MenuItem::linkToCrud('admin.menu.aromatic_compounds', 'fa fa-atom', AromaticCompound::class);
        yield MenuItem::linkToCrud('admin.menu.alchemy_flavors', 'fa fa-flask', AlchemyFlavors::class);
        yield MenuItem::linkToCrud('admin.menu.spicy_types', 'fa fa-tag', SpicyType::class);

        yield MenuItem::section('admin.menu.section_preparation');
        yield MenuItem::linkToCrud('admin.menu.cooking_tips', 'fa fa-fire', CookingTips::class);
        yield MenuItem::linkToCrud('admin.menu.preparation_tips', 'fa fa-mortar-pestle', PreparationTips::class);
        yield MenuItem::linkToCrud('admin.menu.preparation_methods', 'fa fa-list-check', PreparationMethods::class);

        yield MenuItem::section('admin.menu.section_gamification');
        yield MenuItem::linkToCrud('admin.menu.achievements', 'fa fa-trophy', Achievement::class);
        yield MenuItem::linkToCrud('admin.menu.game_sessions', 'fa fa-gamepad', GameSession::class);

        yield MenuItem::section('admin.menu.section_users');
        yield MenuItem::linkToCrud('admin.menu.users', 'fa fa-users', Users::class);
        yield MenuItem::linkToCrud('admin.menu.contact', 'fa fa-envelope', Contact::class);

        yield MenuItem::section('');
        yield MenuItem::linkToRoute('admin.menu.site', 'fa fa-external-link', 'home');
    }

    public function configureCrud(): Crud
    {
        // Le domaine de traduction est défini globalement via Dashboard::setTranslationDomain()
        // (Crud::setTranslationDomain() n'existe pas dans cette version d'EasyAdmin).
        return Crud::new()
            ->setDefaultSort([
                'created_at' => 'DESC',
            ])
            ->setPaginatorPageSize(30);
    }
}
