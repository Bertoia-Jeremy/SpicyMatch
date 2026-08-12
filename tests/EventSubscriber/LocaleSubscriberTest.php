<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\Users;
use App\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AllowMockObjectsWithoutExpectations]
final class LocaleSubscriberTest extends TestCase
{
    public function testRouteLocaleWinsAndIsPersistedInSession(): void
    {
        $request = $this->requestWithSession();
        $request->attributes->set('_locale', 'en');

        $this->dispatch($request, user: null);

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $request->getSession()->get('_locale'));
    }

    public function testUnsupportedRouteLocaleFallsThrough(): void
    {
        $request = $this->requestWithSession();
        $request->attributes->set('_locale', 'de');

        $this->dispatch($request, user: $this->userWithLocale('es'));

        self::assertSame('es', $request->getLocale());
    }

    public function testAuthenticatedUserLocaleUsedWhenNoRouteLocale(): void
    {
        $request = $this->requestWithSession();

        $this->dispatch($request, user: $this->userWithLocale('es'));

        self::assertSame('es', $request->getLocale());
    }

    public function testUserLocaleWinsOverConcurrentSessionLocale(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()
            ->set('_locale', 'en');

        $this->dispatch($request, user: $this->userWithLocale('es'));

        self::assertSame('es', $request->getLocale());
    }

    public function testUnsupportedUserLocaleFallsThroughToSession(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()
            ->set('_locale', 'en');

        $this->dispatch($request, user: $this->userWithLocale('it'));

        self::assertSame('en', $request->getLocale());
    }

    public function testSessionLocaleUsedForAnonymous(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()
            ->set('_locale', 'en');

        $this->dispatch($request, user: null);

        self::assertSame('en', $request->getLocale());
    }

    public function testAcceptLanguageNegotiationWhenNothingElseMatches(): void
    {
        $request = $this->requestWithSession([
            'HTTP_ACCEPT_LANGUAGE' => 'es-ES,es;q=0.9,en;q=0.8',
        ]);

        $this->dispatch($request, user: null);

        self::assertSame('es', $request->getLocale());
        self::assertSame('es', $request->getSession()->get('_locale'));
    }

    public function testFallsBackToDefaultLocaleWhenNoSignal(): void
    {
        $request = $this->requestWithSession();

        $this->dispatch($request, user: null);

        self::assertSame('fr', $request->getLocale());
    }

    public function testStatelessRequestNeverTouchesSession(): void
    {
        $request = new Request(server: [
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->dispatch($request, user: null);

        self::assertSame('en', $request->getLocale());
        self::assertFalse($request->hasSession());
    }

    public function testSubRequestIsIgnored(): void
    {
        $request = $this->requestWithSession();
        $request->setLocale('es');

        $this->dispatch($request, user: $this->userWithLocale('en'), mainRequest: false);

        self::assertSame('es', $request->getLocale());
    }

    /**
     * @param array<string, string> $server
     */
    private function requestWithSession(array $server = []): Request
    {
        $request = new Request(server: $server);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function userWithLocale(string $locale): Users
    {
        $user = new Users();
        $user->setLocale($locale);

        return $user;
    }

    private function dispatch(Request $request, ?Users $user, bool $mainRequest = true): void
    {
        $token = null;
        if (null !== $user) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')
                ->willReturn($user);
        }

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')
            ->willReturn($token);

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );

        (new LocaleSubscriber($tokenStorage))->onKernelRequest($event);
    }
}
