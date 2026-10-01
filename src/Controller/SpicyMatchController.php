<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Spices;
use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/spicymatch', defaults: [
    '_locale' => 'fr',
])]
class SpicyMatchController extends AbstractController
{
    #[Route('/', name: 'index_spicy_match')]
    public function index(Request $request, SpicesRepository $spicesRepository): Response
    {
        $slug = trim($request->query->getString('spice'));
        $spice = $slug !== '' ? $spicesRepository->findOneByLocalizedSlug($slug, $request->getLocale()) : null;

        return $this->render('spicy_match/index.html.twig', [
            'initialSpiceId' => $spice instanceof Spices && $spice->getDeletedAt() === null ? $spice->getId() : null,
        ]);
    }
}
