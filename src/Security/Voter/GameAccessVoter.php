<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class GameAccessVoter extends Voter
{
    public const string PLAY = 'GAME_PLAY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::PLAY;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof Users
            && ($user->getProgression()?->isGamificationEnabled() ?? true);
    }
}
