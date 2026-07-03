<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CookieConsent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CookieConsentService
{
    private const string COOKIE_NAME = 'sm_consent';
    public const int CURRENT_VERSION = 1;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function hasConsented(): bool
    {
        $data = $this->getConsentFromCookie();
        if (null === $data) {
            return false;
        }

        return ($data['version'] ?? 0) >= self::CURRENT_VERSION;
    }

    /**
     * @return array{analytics?: bool, functional?: bool, version?: int, timestamp?: int}|null
     */
    public function getConsent(): ?array
    {
        return $this->getConsentFromCookie();
    }

    public function saveConsent(bool $analytics, bool $functional): CookieConsent
    {
        $request = $this->requestStack->getCurrentRequest();

        $consent = new CookieConsent();
        $consent->setSessionId($request?->getSession()->getId() ?? bin2hex(random_bytes(16)));
        $consent->setAnalyticsConsent($analytics);
        $consent->setFunctionalConsent($functional);
        $consent->setConsentVersion(self::CURRENT_VERSION);
        $consent->setIpAddress($request?->getClientIp());
        $consent->setUserAgent(substr((string) $request?->headers->get('User-Agent'), 0, 512));

        $this->em->persist($consent);
        $this->em->flush();

        return $consent;
    }

    public function respectsDnt(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return '1' === $request?->headers->get('DNT');
    }

    public static function getCookieName(): string
    {
        return self::COOKIE_NAME;
    }

    public static function getCurrentVersion(): int
    {
        return self::CURRENT_VERSION;
    }

    /**
     * @return array{analytics?: bool, functional?: bool, version?: int, timestamp?: int}|null
     */
    private function getConsentFromCookie(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);
        if (null === $cookie) {
            return null;
        }

        try {
            return json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
