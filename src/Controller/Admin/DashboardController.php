<?php

namespace App\Controller\Admin;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Contact;
use App\Entity\Spices;
use App\Entity\SpicyType;
use App\Entity\Users;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('SpicyMatch');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', '');
        yield MenuItem::linkToRoute('Site SpicyMatch', '', 'home');
        yield MenuItem::linkToCrud('Groupes aromatiques', '', AromaticGroups::class);
        yield MenuItem::linkToCrud('Epices', '', Spices::class);
        yield MenuItem::linkToCrud('Composants aromatiques', '', AromaticCompound::class);
        yield MenuItem::linkToCrud('Alchimie des saveurs', '', AlchemyFlavors::class);
        yield MenuItem::linkToCrud('Types d\'épices', '', SpicyType::class);
        yield MenuItem::linkToCrud('Users', '', Users::class);
        yield MenuItem::linkToCrud('Contact', '', Contact::class);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            // this defines the pagination size for all CRUD controllers
            // (each CRUD controller can override this value if needed)
                ->setDefaultSort(['created_at' => 'DESC'])
            ->setPaginatorPageSize(30)
            ;
    }
}
