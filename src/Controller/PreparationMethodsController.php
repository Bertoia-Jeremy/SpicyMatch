<?php

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\PreparationMethodsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/preparation/methods', defaults: [
    '_locale' => 'fr',
])]
class PreparationMethodsController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_preparation_methods', methods: ['GET'])]
    public function index(PreparationMethodsRepository $preparationMethodsRepository): Response
    {
        return $this->render('preparation_methods/index.html.twig', [
            'preparationMethods' => $preparationMethodsRepository->findAll(),
        ]);
    }

    #[Route('/{slug}', name: 'view_preparation_methods', methods: ['GET'])]
    public function view(string $slug, Request $request, PreparationMethodsRepository $repository): Response
    {
        $locale = $request->getLocale();
        $preparationMethod = $repository->findOneByLocalizedSlug($slug, $locale);
        if (null === $preparationMethod) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_preparation_methods',
            $slug,
            $preparationMethod->getLocalizedSlug($locale),
            $locale
        )) !== null) {
            return $redirect;
        }

        // Seed the server-side timestamp for the "temps_de_l_infusion" easter egg
        // (stay ≥ 260s on the infusion page). Client cannot forge this value —
        // the EasterEggService reads it from session on validation.
        if ('infusion' === mb_strtolower((string) $preparationMethod->getName())) {
            $session = $request->getSession();
            if (! \is_int($session->get('easter_egg.infusion_started_at'))) {
                $session->set('easter_egg.infusion_started_at', time());
            }
        }

        return $this->render('preparation_methods/view.html.twig', [
            'preparationMethod' => $preparationMethod,
            'hreflang_slugs' => [
                'fr' => $preparationMethod->getLocalizedSlug('fr'),
                'en' => $preparationMethod->getLocalizedSlug('en'),
                'es' => $preparationMethod->getLocalizedSlug('es'),
            ],
        ]);
    }
}
