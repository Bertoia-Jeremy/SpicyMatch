<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CookieConsentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[AllowMockObjectsWithoutExpectations]
class CookieConsentServiceTest extends TestCase
{
    private EntityManagerInterface $em;

    private RequestStack $requestStack;

    private CookieConsentService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = new RequestStack();
        $this->service = new CookieConsentService($this->em, $this->requestStack);
    }

    public function testHasConsentedReturnsFalseWithoutCookie(): void
    {
        $request = Request::create('/');
        $this->requestStack->push($request);

        self::assertFalse($this->service->hasConsented());
    }

    public function testHasConsentedReturnsTrueWithValidCookie(): void
    {
        $cookieData = json_encode([
            'analytics' => true,
            'functional' => true,
            'version' => CookieConsentService::CURRENT_VERSION,
            'timestamp' => time(),
        ]);

        $request = Request::create('/');
        $request->cookies->set('sm_consent', $cookieData);
        $this->requestStack->push($request);

        self::assertTrue($this->service->hasConsented());
    }

    public function testHasConsentedReturnsFalseWhenVersionOutdated(): void
    {
        $cookieData = json_encode([
            'analytics' => true,
            'functional' => true,
            'version' => 0,
            'timestamp' => time(),
        ]);

        $request = Request::create('/');
        $request->cookies->set('sm_consent', $cookieData);
        $this->requestStack->push($request);

        self::assertFalse($this->service->hasConsented());
    }

    public function testHasConsentedReturnsFalseWithInvalidJson(): void
    {
        $request = Request::create('/');
        $request->cookies->set('sm_consent', 'not-valid-json');
        $this->requestStack->push($request);

        self::assertFalse($this->service->hasConsented());
    }

    public function testSaveConsentPersistsToDatabase(): void
    {
        $request = Request::create('/');
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        ));
        $this->requestStack->push($request);

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $consent = $this->service->saveConsent(true, false);

        self::assertTrue($consent->isAnalyticsConsent());
        self::assertFalse($consent->isFunctionalConsent());
        self::assertSame(CookieConsentService::CURRENT_VERSION, $consent->getConsentVersion());
    }

    public function testRespectsDntReturnsTrueWhenDntHeaderSet(): void
    {
        $request = Request::create('/');
        $request->headers->set('DNT', '1');
        $this->requestStack->push($request);

        self::assertTrue($this->service->respectsDnt());
    }

    public function testRespectsDntReturnsFalseWhenNoDntHeader(): void
    {
        $request = Request::create('/');
        $this->requestStack->push($request);

        self::assertFalse($this->service->respectsDnt());
    }

    public function testRespectsDntReturnsFalseWhenDntHeaderIsZero(): void
    {
        $request = Request::create('/');
        $request->headers->set('DNT', '0');
        $this->requestStack->push($request);

        self::assertFalse($this->service->respectsDnt());
    }

    public function testGetConsentReturnsNullWithoutCookie(): void
    {
        $request = Request::create('/');
        $this->requestStack->push($request);

        self::assertNull($this->service->getConsent());
    }

    public function testGetConsentReturnsDecodedCookieData(): void
    {
        $data = [
            'analytics' => true,
            'functional' => false,
            'version' => 1,
            'timestamp' => 1234567890,
        ];

        $request = Request::create('/');
        $request->cookies->set('sm_consent', json_encode($data));
        $this->requestStack->push($request);

        self::assertSame($data, $this->service->getConsent());
    }

    public function testGetCookieNameReturnsExpectedValue(): void
    {
        self::assertSame('sm_consent', CookieConsentService::getCookieName());
    }

    public function testGetCurrentVersionReturnsExpectedValue(): void
    {
        self::assertSame(CookieConsentService::CURRENT_VERSION, CookieConsentService::getCurrentVersion());
    }

    public function testHasConsentedReturnsFalseWithoutRequest(): void
    {
        // No request pushed to stack
        self::assertFalse($this->service->hasConsented());
    }
}
