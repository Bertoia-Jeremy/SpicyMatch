<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Message\EasterEggFoundEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/secret')]
#[IsGranted('ROLE_USER')]
class EasterEggController extends AbstractController
{
    /**
     * Triggered by hidden mechanisms (secret URLs, Konami code, etc.).
     * The XP amount can vary per egg — defaults to 75 if not overridden.
     */
    #[Route('/{slug}', name: 'easter_egg_trigger', methods: ['POST'])]
    public function trigger(string $slug, MessageBusInterface $bus): JsonResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $bus->dispatch(new EasterEggFoundEvent($user->getId(), $slug));

        return $this->json([
            'status' => 'ok',
        ]);
    }
}
