<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GdprRequest;
use App\Form\GdprRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/confidentialite/mes-droits', defaults: [
    '_locale' => 'fr',
])]
class GdprRequestController extends AbstractController
{
    #[Route('/', name: 'gdpr_request')]
    public function request(Request $request, EntityManagerInterface $entityManager): Response
    {
        $gdprRequest = new GdprRequest();

        $form = $this->createForm(GdprRequestFormType::class, $gdprRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($gdprRequest);
            $entityManager->flush();

            return $this->redirectToRoute('gdpr_request_success');
        }

        return $this->render('gdpr/request.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && ! $form->isValid() ? 422 : 200));
    }

    #[Route('/confirmation', name: 'gdpr_request_success')]
    public function success(): Response
    {
        return $this->render('gdpr/success.html.twig');
    }
}
