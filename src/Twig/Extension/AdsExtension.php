<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdsExtension extends AbstractExtension
{
    private const array PROVIDER_TEMPLATES = [
        'ethicalads' => 'partials/ads/_ethicalads.html.twig',
        'carbon' => 'partials/ads/_carbon.html.twig',
        'placeholder' => 'partials/ads/_placeholder.html.twig',
    ];

    private const string DEFAULT_PROVIDER = 'ethicalads';

    private const string DEV_ONLY_PROVIDER = 'placeholder';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly bool $enabled,
        private readonly string $provider,
        private readonly string $publisherId,
        private readonly string $environment,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ads_enabled', $this->adsEnabled(...)),
            new TwigFunction('ads_provider', $this->adsProvider(...)),
            new TwigFunction('ads_template', $this->adsTemplate(...)),
            new TwigFunction('ads_publisher_id', $this->adsPublisherId(...)),
        ];
    }

    public function adsEnabled(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        return ! ($user instanceof Users && $user->isPremium());
    }

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

    public function adsTemplate(): string
    {
        return self::PROVIDER_TEMPLATES[$this->adsProvider()];
    }

    public function adsPublisherId(): string
    {
        return $this->publisherId;
    }
}
