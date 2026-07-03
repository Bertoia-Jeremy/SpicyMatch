<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}', defaults: [
    '_locale' => 'fr',
])]
class SearchController extends AbstractController
{
    #[Route('/recherche', name: 'search_results', methods: ['GET'])]
    public function index(Request $request, SpicesRepository $spicesRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $results = [];

        if ('' !== $query && mb_strlen($query) >= 2) {
            $results = $spicesRepository->search($query, $request->getLocale());
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
