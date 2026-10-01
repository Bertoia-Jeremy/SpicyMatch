<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\EventSubscriber\LocaleSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

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

        $target = $request->query->getString('target');
        if (self::isInternalPath($target)) {
            return $this->redirect($target);
        }

        return $this->redirectToRoute('home');
    }

    private static function isInternalPath(string $target): bool
    {
        return str_starts_with($target, '/')
            && ! str_starts_with($target, '//')
            && preg_match('/[\x00-\x1F\x7F\\\\]/', $target) !== 1;
    }
}
