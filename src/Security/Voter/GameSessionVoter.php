<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\GameSession;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, GameSession>
 */
final class GameSessionVoter extends Voter
{
    public const string OWNER = 'GAME_SESSION_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::OWNER && $subject instanceof GameSession;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user !== null && $subject->getUser() === $user;
    }
}
