<?php

namespace App\Controller;

use App\Entity\Users;
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
    #[Route('/', name: 'dashboard_user', methods: ['GET'])]
    public function index(UsersRepository $usersRepository): Response
    {
        return $this->render('users/dashboard.html.twig', [
            'users' => $usersRepository->findAll(),
        ]);
    }

    #[Route('/history', name: 'history_user', methods: ['GET'])]
    public function history(Request $request): Response
    {
        return $this->render('users/history.html.twig', [
            'user' => "history",
        ]);
    }

    #[Route('/security', name: 'security_user', methods: ['GET'])]
    public function security(Request $request): Response
    {
        return $this->render('users/security.html.twig', [
            'user' => "security",
        ]);
    }

    #[Route('/configuration', name: 'configuration_user', methods: ['GET'])]
    public function configuration(Request $request): Response
    {
        return $this->render('users/configuration.html.twig', [
            'user' => "configuration",
        ]);
    }

    #[Route('/profile', name: 'profile_user', methods: ['GET'])]
    public function profile(Request $request): Response
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
