<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Users;
use App\Enum\ContentKind;
use App\Gamification\ContentReadTicket;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class GamificationExtension
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ContentReadTicket $contentReadTicket,
    ) {
    }

    #[AsTwigFunction(name: 'gamification_on')]
    public function gamificationOn(): bool
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return ! $user instanceof Users
            || ($user->getProgression()?->isGamificationEnabled() ?? true);
    }

    #[AsTwigFunction(name: 'content_read_ticket')]
    public function contentReadTicket(string $kind, ?int $contentId): ?string
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (! $user instanceof Users || $user->getId() === null || $contentId === null) {
            return null;
        }

        return $this->contentReadTicket->issue(ContentKind::from($kind), $user->getId(), $contentId);
    }
}
