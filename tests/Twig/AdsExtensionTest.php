<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\Users;
use App\Twig\Extension\AdsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class AdsExtensionTest extends TestCase
{
    public function testDisabledFlagWinsOverEverything(): void
    {
        $extension = $this->createExtension(enabled: false, user: null);

        self::assertFalse($extension->adsEnabled());
    }

    public function testEnabledForAnonymousVisitor(): void
    {
        $extension = $this->createExtension(enabled: true, user: null);

        self::assertTrue($extension->adsEnabled());
    }

    public function testEnabledForFreeUser(): void
    {
        $extension = $this->createExtension(enabled: true, user: new Users());

        self::assertTrue($extension->adsEnabled());
    }

    public function testEnabledForExpiredPremiumUser(): void
    {
        $user = new Users();
        $user->setPremiumUntil(new \DateTimeImmutable('-1 day'));

        $extension = $this->createExtension(enabled: true, user: $user);

        self::assertTrue($extension->adsEnabled());
    }

    public function testDisabledForPremiumUser(): void
    {
        $user = new Users();
        $user->setPremiumUntil(new \DateTimeImmutable('+1 month'));

        $extension = $this->createExtension(enabled: true, user: $user);

        self::assertFalse($extension->adsEnabled());
    }

    public function testKnownProviderIsReturned(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, provider: 'carbon');

        self::assertSame('carbon', $extension->adsProvider());
    }

    public function testUnknownProviderFallsBackToEthicalads(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, provider: 'adsense');

        self::assertSame('ethicalads', $extension->adsProvider());
    }

    public function testTemplateMatchesProvider(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, provider: 'carbon');

        self::assertSame('partials/ads/_carbon.html.twig', $extension->adsTemplate());
    }

    public function testTemplateFallsBackToEthicaladsForUnknownProvider(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, provider: 'adsense');

        self::assertSame('partials/ads/_ethicalads.html.twig', $extension->adsTemplate());
    }

    public function testPlaceholderUsableInDev(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, provider: 'placeholder', environment: 'dev');

        self::assertSame('placeholder', $extension->adsProvider());
        self::assertSame('partials/ads/_placeholder.html.twig', $extension->adsTemplate());
    }

    public function testPlaceholderFallsBackToEthicaladsInProd(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, provider: 'placeholder', environment: 'prod');

        self::assertSame('ethicalads', $extension->adsProvider());
        self::assertSame('partials/ads/_ethicalads.html.twig', $extension->adsTemplate());
    }

    public function testPublisherIdIsExposed(): void
    {
        $extension = $this->createExtension(enabled: true, user: null, publisherId: 'spicymatch-io');

        self::assertSame('spicymatch-io', $extension->adsPublisherId());
    }

    private function createExtension(
        bool $enabled,
        ?Users $user,
        string $provider = 'ethicalads',
        string $publisherId = '',
        string $environment = 'test',
    ): AdsExtension {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);

        if (null !== $user) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')
                ->willReturn($user);
            $tokenStorage->method('getToken')
                ->willReturn($token);
        } else {
            $tokenStorage->method('getToken')
                ->willReturn(null);
        }

        return new AdsExtension($tokenStorage, $enabled, $provider, $publisherId, $environment);
    }
}
