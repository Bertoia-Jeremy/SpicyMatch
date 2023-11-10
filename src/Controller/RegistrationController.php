<?php

declare(strict_types=1);

namespace App\Controller;

use App\Factory\UsersFactory;
use App\Form\RegistrationFormType;
use App\Repository\UsersRepository;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UsersFactory $usersFactory,
        private readonly UsersRepository $usersRepository
    ) {
    }

    #[Route('/register', name: 'index_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserAuthenticatorInterface $authenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->usersFactory->create();
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')
                        ->getData()
                )
            );
            $this->usersRepository->add($user, true);

            return $authenticator->authenticateUser(
                $user,
                $loginFormAuthenticator,
                $request
            );
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
