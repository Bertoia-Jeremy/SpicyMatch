<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/contact')]
class ContactController extends AbstractController
{
    #[Route('/', name: 'new_contact')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        // just set up a fresh $contact object (remove the example data)
        $contact = new Contact();

        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $contact = $form->getData();
            $contact->setCreatedAt(new \DateTime())
                    ->setUpdatedAt(new \DateTime())
                    ->setIsTreated(false);

            $em->persist($contact);
            $em->flush();

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
