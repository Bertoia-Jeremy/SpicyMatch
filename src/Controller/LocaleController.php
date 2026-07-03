<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\EventSubscriber\LocaleSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Bascule de langue (i18n). Mémorise la locale choisie en session et,
 * pour un utilisateur connecté, la persiste sur son compte (Users::$locale).
 * Redirige vers la page d'origine (referer interne) sinon l'accueil.
 */
class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'switch_locale', methods: ['GET'], requirements: [
        'locale' => 'fr|en|es',
    ])]
    public function switch(string $locale, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (in_array($locale, LocaleSubscriber::SUPPORTED_LOCALES, true)) {
            if ($request->hasSession()) {
                $request->getSession()
                    ->set('_locale', $locale);
            }

            $user = $this->getUser();
            if ($user instanceof Users) {
                $user->setLocale($locale);
                $em->flush();
            }
        }

        // Redirection sûre : referer interne uniquement (host EXACT, slash final
        // pour éviter `host.evil.com`), sinon accueil.
        $referer = $request->headers->get('referer');
        $base = $request->getSchemeAndHttpHost();
        if (is_string($referer) && ($referer === $base || str_starts_with($referer, $base.'/'))) {
            return $this->redirect($this->rewriteLocaleInUrl($referer, $base, $locale));
        }

        return $this->redirectToRoute('home');
    }

    /**
     * Réécrit le préfixe /{locale} du referer (sinon la route _locale, prioritaire
     * dans LocaleSubscriber, réaffiche l'ancienne langue). URLs non préfixées intactes.
     */
    private function rewriteLocaleInUrl(string $url, string $base, string $locale): string
    {
        $path = substr($url, strlen($base));
        $rewritten = preg_replace('#^/(fr|en|es)(?=/|$|\?|\#)#', '/'.$locale, $path, 1);

        return $base.($rewritten ?? $path);
    }
}
