<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Security\Voter\SpicyMatchHistoryVoter;
use App\Service\Guest\GuestHistoryRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class SpicyMatchHistoryVoterTest extends TestCase
{
    private const int GUEST_ID = 10;

    private const int OTHER_ID = 11;

    private static Users $owner;

    /**
     * @return iterable<string, array{0: \Closure(): SpicyMatchHistory, 1: \Closure(): TokenInterface, 2: int}>
     */
    public static function voteProvider(): iterable
    {
        $owner = static fn (): TokenInterface => new UsernamePasswordToken(self::$owner, 'main', ['ROLE_USER']);
        $stranger = static fn (): TokenInterface => new UsernamePasswordToken(new Users(), 'main', ['ROLE_USER']);
        $anonymous = static fn (): TokenInterface => new NullToken();

        yield 'owner on own history' => [
            static fn (): SpicyMatchHistory => self::history(self::OTHER_ID, self::$owner),
            $owner,
            VoterInterface::ACCESS_GRANTED,
        ];
        yield 'stranger on owned history' => [
            static fn (): SpicyMatchHistory => self::history(self::OTHER_ID, self::$owner),
            $stranger,
            VoterInterface::ACCESS_DENIED,
        ];
        yield 'anonymous on owned history' => [
            static fn (): SpicyMatchHistory => self::history(self::GUEST_ID, self::$owner),
            $anonymous,
            VoterInterface::ACCESS_DENIED,
        ];
        yield 'anonymous on guest history in session' => [
            static fn (): SpicyMatchHistory => self::history(self::GUEST_ID, null),
            $anonymous,
            VoterInterface::ACCESS_GRANTED,
        ];
        yield 'anonymous on guest history of another session' => [
            static fn (): SpicyMatchHistory => self::history(self::OTHER_ID, null),
            $anonymous,
            VoterInterface::ACCESS_DENIED,
        ];
        yield 'deleted guest history' => [
            static fn (): SpicyMatchHistory => self::history(self::GUEST_ID, null)->setDeletedAt(new \DateTimeImmutable()),
            $anonymous,
            VoterInterface::ACCESS_DENIED,
        ];
    }

    /**
     * @param \Closure(): SpicyMatchHistory $history
     * @param \Closure(): TokenInterface    $token
     */
    #[DataProvider('voteProvider')]
    public function testVote(\Closure $history, \Closure $token, int $expected): void
    {
        self::$owner = new Users();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $registry = new GuestHistoryRegistry(new RequestStack([$request]));
        $registry->remember(self::GUEST_ID);

        self::assertSame($expected, new SpicyMatchHistoryVoter($registry)->vote($token(), $history(), [SpicyMatchHistoryVoter::OWNER]));
    }

    private static function history(int $id, ?Users $user): SpicyMatchHistory
    {
        $history = new SpicyMatchHistory()
            ->setSpicyMatch(new SpicyMatch()->setUser($user));
        new \ReflectionProperty(SpicyMatchHistory::class, 'id')->setValue($history, $id);

        return $history;
    }
}
