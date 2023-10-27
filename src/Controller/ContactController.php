<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/contact")
 */
class ContactController extends AbstractController
{
    /**
     * @Route("/", name="new_contact")
     */
    public function new(Request $request): Response
    {
        // just set up a fresh $contact object (remove the example data)
        $contact = new Contact();

        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$contact` variable has also been updated
            $contact = $form->getData();
            $contact = new Contact();
            $contact->setCreatedAt(new \DateTime())
                    ->setUpdatedAt(new \DateTime())
                    ->setIsTreated(false);
            
           // $entityManager->persist($contact);
         //   $entityManager->flush();

            // ... perform some action, such as saving the Contact to the database

            return $this->redirectToRoute('contact_success');
        }

        return $this->render('contact/new.html.twig', [
            'form' => $form,
        ]);
    }
}
