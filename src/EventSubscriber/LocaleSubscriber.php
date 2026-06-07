<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Users;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Résout la locale de chaque requête (i18n). Ordre de priorité :
 *   1. paramètre de route `_locale` (futur préfixe /{_locale})
 *   2. locale persistée de l'utilisateur connecté (Users::$locale)
 *   3. locale stockée en session (_locale)
 *   4. négociation Accept-Language ∩ locales supportées
 *   5. locale par défaut du framework (fr)
 *
 * La locale retenue est posée sur la requête ET mémorisée en session,
 * de sorte que la navigation suivante reste cohérente sans préfixe d'URL.
 * Priorité haute (avant le RouterListener par défaut de Symfony).
 */
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
        // Priorité 20 : après le LocaleListener Symfony (qui lit _locale depuis les attributs),
        // mais on garde la main pour appliquer user/session/Accept-Language quand _locale est absent.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Les requêtes sans session (ex. API stateless) ne peuvent pas mémoriser la locale :
        // on résout sans jamais toucher la session pour éviter SessionNotFoundException.
        $hasSession = $request->hasSession();

        // 1. Paramètre de route explicite (préfixe /{_locale}) — prioritaire et persisté.
        $routeLocale = $request->attributes->get('_locale');
        if (is_string($routeLocale) && $this->isSupported($routeLocale)) {
            $request->setLocale($routeLocale);
            if ($hasSession) {
                $request->getSession()
                    ->set('_locale', $routeLocale);
            }

            return;
        }

        // 2. Utilisateur connecté.
        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof Users && $this->isSupported($user->getLocale())) {
            $request->setLocale($user->getLocale());

            return;
        }

        // 3. Session.
        if ($hasSession) {
            $sessionLocale = $request->getSession()
                ->get('_locale');
            if (is_string($sessionLocale) && $this->isSupported($sessionLocale)) {
                $request->setLocale($sessionLocale);

                return;
            }
        }

        // 4. Négociation Accept-Language, sinon 5. défaut.
        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES) ?? $this->defaultLocale;
        $request->setLocale($preferred);
        if ($hasSession) {
            $request->getSession()
                ->set('_locale', $preferred);
        }
    }

    private function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }
}
