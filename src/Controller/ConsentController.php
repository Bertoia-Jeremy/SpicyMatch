<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CookieConsentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConsentController extends AbstractController
{
    public function __construct(
        private readonly CookieConsentService $consentService,
    ) {
    }

    #[Route('/consent/save', name: 'consent_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
            ], 400);
        }

        if (! $this->isCsrfTokenValid('cookie_consent', $data['_token'] ?? '')) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $analytics = (bool) ($data['analytics'] ?? false);
        $functional = (bool) ($data['functional'] ?? false);

        // Respect DNT: override analytics to false
        if ($this->consentService->respectsDnt()) {
            $analytics = false;
        }

        $consent = $this->consentService->saveConsent($analytics, $functional);

        $cookieData = json_encode([
            'analytics' => $analytics,
            'functional' => $functional,
            'version' => CookieConsentService::getCurrentVersion(),
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR);

        $response = new JsonResponse([
            'status' => 'ok',
        ]);
        $response->headers->setCookie(
            Cookie::create(CookieConsentService::getCookieName())
                ->withValue($cookieData)
                ->withExpires(new \DateTimeImmutable('+13 months'))
                ->withPath('/')
                ->withSameSite('lax')
        );

        return $response;
    }
}
