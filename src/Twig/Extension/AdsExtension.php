<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class AdsExtension
{
    private const array PROVIDER_TEMPLATES = [
        'ethicalads' => 'partials/ads/_ethicalads.html.twig',
        'carbon' => 'partials/ads/_carbon.html.twig',
        'placeholder' => 'partials/ads/_placeholder.html.twig',
    ];

    private const string DEFAULT_PROVIDER = 'ethicalads';

    private const string DEV_ONLY_PROVIDER = 'placeholder';

    private const array SINGLE_SLOT_PROVIDERS = ['carbon'];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private bool $enabled,
        private string $provider,
        private string $publisherId,
        private string $environment,
    ) {
    }

    #[AsTwigFunction(name: 'ads_enabled')]
    public function adsEnabled(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        return ! $user instanceof Users || ! $user->isPremium();
    }

    #[AsTwigFunction(name: 'ads_provider')]
    public function adsProvider(): string
    {
        if (! isset(self::PROVIDER_TEMPLATES[$this->provider])) {
            return self::DEFAULT_PROVIDER;
        }

        if ($this->provider === self::DEV_ONLY_PROVIDER && $this->environment === 'prod') {
            return self::DEFAULT_PROVIDER;
        }

        return $this->provider;
    }

    #[AsTwigFunction(name: 'ads_template')]
    public function adsTemplate(): string
    {
        return self::PROVIDER_TEMPLATES[$this->adsProvider()];
    }

    #[AsTwigFunction(name: 'ads_multi_slot')]
    public function adsMultiSlot(): bool
    {
        return ! \in_array($this->adsProvider(), self::SINGLE_SLOT_PROVIDERS, true);
    }

    #[AsTwigFunction(name: 'ads_publisher_id')]
    public function adsPublisherId(): string
    {
        return $this->publisherId;
    }
}
