<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Users;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string> source unique des locales supportées (UI, négociation, requirements de route)
     */
    public const SUPPORTED_LOCALES = ['fr', 'en', 'es'];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $defaultLocale = 'fr',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
            KernelEvents::EXCEPTION => ['onKernelException', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $hasSession = $request->hasSession();

        $routeLocale = $request->attributes->get('_locale');
        if (is_string($routeLocale) && $this->isSupported($routeLocale)) {
            $request->setLocale($routeLocale);
            if ($hasSession) {
                $request->getSession()
                    ->set('_locale', $routeLocale);
            }

            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof Users && $this->isSupported($user->getLocale())) {
            $request->setLocale($user->getLocale());

            return;
        }

        if ($hasSession) {
            $sessionLocale = $request->getSession()
                ->get('_locale');
            if (is_string($sessionLocale) && $this->isSupported($sessionLocale)) {
                $request->setLocale($sessionLocale);

                return;
            }
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES) ?? $this->defaultLocale;
        $request->setLocale($preferred);
        if ($hasSession) {
            $request->getSession()
                ->set('_locale', $preferred);
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $routeLocale = $request->attributes->get('_locale');
        if (is_string($routeLocale) && $this->isSupported($routeLocale)) {
            return;
        }

        $request->setLocale($this->resolveReadOnly($request));
    }

    private function resolveReadOnly(Request $request): string
    {
        $pathLocale = $this->localeFromPath($request->getPathInfo());
        if ($pathLocale !== null) {
            return $pathLocale;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof Users && $this->isSupported($user->getLocale())) {
            return $user->getLocale();
        }

        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $sessionLocale = $request->getSession()
                ->get('_locale');
            if (is_string($sessionLocale) && $this->isSupported($sessionLocale)) {
                return $sessionLocale;
            }
        }

        return $request->getPreferredLanguage(self::SUPPORTED_LOCALES) ?? $this->defaultLocale;
    }

    private function localeFromPath(string $pathInfo): ?string
    {
        $segment = explode('/', trim($pathInfo, '/'), 2)[0];

        return $this->isSupported($segment) ? $segment : null;
    }

    private function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }
}
