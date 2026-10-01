<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\EventSubscriber\GuestHistoryClaimSubscriber;
use App\Message\FavoriteToggledEvent;
use App\Message\MatchSavedEvent;
use App\Repository\SpicyMatchHistoryRepository;
use App\Service\Guest\GuestHistoryRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class GuestHistoryClaimSubscriberTest extends TestCase
{
    private Request $request;

    private GuestHistoryRegistry $registry;

    /**
     * @var list<object>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->request = Request::create('/login');
        $this->request->setSession(new Session(new MockArraySessionStorage()));
        $this->registry = new GuestHistoryRegistry(new RequestStack([$this->request]));
    }

    public function testClaimsGuestHistoriesAppliesPendingFavoriteAndRewardsSealedOnes(): void
    {
        $user = $this->user(5);
        $sealed = $this->history(1, true);
        $draft = $this->history(2, false);
        $this->registry->remember(1);
        $this->registry->remember(2);
        $this->registry->markFavoritePending(2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('flush');

        $this->subscriber([$sealed, $draft], $em)->onLoginSuccess($this->event($user));

        self::assertSame($user, $sealed->getSpicyMatch()?->getUser());
        self::assertSame($user, $draft->getSpicyMatch()?->getUser());
        self::assertFalse($sealed->isFavorite());
        self::assertTrue($draft->isFavorite());
        self::assertEquals([new MatchSavedEvent(1, 5), new FavoriteToggledEvent(5)], $this->dispatched);
        self::assertFalse($this->registry->owns(1));
    }

    public function testDoesNothingWithoutGuestHistory(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())
            ->method('flush');

        $this->subscriber([], $em)->onLoginSuccess($this->event($this->user(5)));

        self::assertSame([], $this->dispatched);
    }

    /**
     * @param list<SpicyMatchHistory> $found
     */
    private function subscriber(array $found, EntityManagerInterface $em): GuestHistoryClaimSubscriber
    {
        $repository = $this->createStub(SpicyMatchHistoryRepository::class);
        $repository->method('findGuestHistories')
            ->willReturn($found);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (object $message): Envelope {
                $this->dispatched[] = $message;

                return new Envelope($message);
            });

        return new GuestHistoryClaimSubscriber($this->registry, $repository, $em, $bus, new MockClock());
    }

    private function event(Users $user): LoginSuccessEvent
    {
        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('guest', static fn (): Users => $user)),
            new UsernamePasswordToken($user, 'main', ['ROLE_USER']),
            $this->request,
            null,
            'main',
        );
    }

    private function user(int $id): Users
    {
        $user = new Users();
        new \ReflectionProperty(Users::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function history(int $id, bool $sealed): SpicyMatchHistory
    {
        $history = new SpicyMatchHistory()
            ->setSpicyMatch(new SpicyMatch());
        new \ReflectionProperty(SpicyMatchHistory::class, 'id')->setValue($history, $id);
        if ($sealed) {
            new \ReflectionProperty(SpicyMatchHistory::class, 'sealedAt')->setValue($history, new \DateTimeImmutable());
        }

        return $history;
    }
}
