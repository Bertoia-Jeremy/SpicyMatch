<?php

declare(strict_types=1);

namespace App\Controller;

use App\Factory\ContactFactory;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/contact')]
class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactFactory $contactFactory,
        private readonly ContactRepository $contactRepository
    ) {
    }

    #[Route('/', name: 'new_contact')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = $this->contactFactory->create();

        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact = $form->getData();
            
            $entityManager->persist($contact);
            $entityManager->flush();

            return $this->redirectToRoute('contact_success_form');
        }

        return $this->render('contact/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/success_form', name: 'contact_success_form')]
    public function successForm(): Response
    {
        return $this->render('contact/success_form.html.twig');
    }
}
