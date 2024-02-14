<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UsersMailType;
use App\Form\UsersType;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/users')]
class UsersController extends AbstractController
{
    public function __construct(
        private readonly UsersRepository $usersRepository
    ) {
    }

    #[Route('/', name: 'dashboard_user', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('users/dashboard.html.twig', [
        ]);
    }

    #[Route('/history', name: 'history_user', methods: ['GET'])]
    public function history(): Response
    {
        return $this->render('users/history.html.twig', [
            'user' => "history",
        ]);
    }

    #[Route('/security', name: 'security_user', methods: ['GET'])]
    public function security(): Response
    {
        return $this->render('users/security.html.twig', [
            'user' => "security",
        ]);
    }

    #[Route('/configuration', name: 'configuration_user', methods: ['GET'])]
    public function configuration(): Response
    {
        $user = $this->getUser();

        return $this->render('users/configuration.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/userMail', name: 'mail_user', methods: ['GET','POST'])]
    public function userMail(Request $request): Response
    {
        $user = $this->getUser();
       
        $formMail = $this->createForm(UsersMailType::class, $user);
        $formMail->handleRequest($request);

        if ($formMail->isSubmitted() && $formMail->isValid()) {
            $user = $formMail->getData();

            $this->usersRepository->addOrUpdate($user);

            return $this->redirectToRoute('configuration_user');
        }

        return $this->render('users/_form_mail.html.twig', [
            'user' => $user,
            'form' => $formMail,
        ]);
    }

    #[Route('/profile', name: 'profile_user', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('users/profile.html.twig', [
            'user' => "profile",
        ]);
    }
    
    #[Route('/{id}', name: 'delete_user', methods: ['POST'])]
    public function delete(Request $request, Users $user, EntityManagerInterface $entityManager): Response
    {
        # TODO => soft delete + revoir la vérification de l'existence d'un pseudo (ajouter le champ delete dans le where et/ou index)
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('index_users_index', [], Response::HTTP_SEE_OTHER);
    }
}
