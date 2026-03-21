<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserProgression;
use App\Entity\UserStat;
use App\Factory\UsersFactory;
use App\Form\RegistrationFormType;
use App\Repository\UsersRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly UsersRepository $usersRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/register', name: 'index_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserAuthenticatorInterface $authenticator,
        LoginFormAuthenticator $loginFormAuthenticator,
    ): Response {
        $user = $this->usersFactory->create();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->usersRepository->addOrUpdate($user);

            // Init gamification entities
            $progression = new UserProgression();
            $progression->setUser($user);
            $user->setProgression($progression);
            $this->em->persist($progression);

            $stats = new UserStat();
            $stats->setUser($user);
            $user->setStats($stats);
            $this->em->persist($stats);

            $this->em->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès !');

            return $authenticator->authenticateUser($user, $loginFormAuthenticator, $request);
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
