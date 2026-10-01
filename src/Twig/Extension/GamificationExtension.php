<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class GamificationExtension
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    #[AsTwigFunction(name: 'gamification_on')]
    public function gamificationOn(): bool
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return ! $user instanceof Users
            || ($user->getProgression()?->isGamificationEnabled() ?? true);
    }
}
