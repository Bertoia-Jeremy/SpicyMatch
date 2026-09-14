<?php

declare(strict_types=1);

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class RootController extends AbstractController
{
    #[Route('/', name: 'root')]
    public function root(Request $request): RedirectResponse
    {
        $locale = $request->getPreferredLanguage(LocaleSubscriber::SUPPORTED_LOCALES)
            ?? $this->getParameter('kernel.default_locale');

        return $this->redirectToRoute('home', [
            '_locale' => $locale,
        ]);
    }
}
