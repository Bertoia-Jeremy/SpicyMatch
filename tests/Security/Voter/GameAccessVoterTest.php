<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Security\Voter\GameAccessVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class GameAccessVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{0: bool|null, 1: int}>
     */
    public static function progressionProvider(): iterable
    {
        yield 'no progression yet' => [null, VoterInterface::ACCESS_GRANTED];
        yield 'gamification enabled' => [true, VoterInterface::ACCESS_GRANTED];
        yield 'gamification disabled' => [false, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('progressionProvider')]
    public function testVoteFollowsGamificationToggle(?bool $enabled, int $expected): void
    {
        $user = new Users();
        if ($enabled !== null) {
            $progression = new UserProgression();
            $enabled ? $progression->enableGamification() : $progression->disableGamification();
            $user->setProgression($progression);
        }

        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        self::assertSame($expected, new GameAccessVoter()->vote($token, null, [GameAccessVoter::PLAY]));
    }

    public function testAnonymousIsDenied(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            new GameAccessVoter()
                ->vote(new NullToken(), null, [GameAccessVoter::PLAY]),
        );
    }

    public function testAbstainsOnOtherAttributes(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            new GameAccessVoter()
                ->vote(new NullToken(), null, ['ROLE_ADMIN']),
        );
    }
}
