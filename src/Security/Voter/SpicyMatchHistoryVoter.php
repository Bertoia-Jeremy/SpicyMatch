<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, SpicyMatchHistory>
 */
final class SpicyMatchHistoryVoter extends Voter
{
    public const string OWNER = 'HISTORY_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::OWNER && $subject instanceof SpicyMatchHistory;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof Users
            && $subject->getDeletedAt() === null
            && $subject->getSpicyMatch()?->getUser() === $user;
    }
}
