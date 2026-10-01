<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\PreparationMethods;
use App\Entity\Spices;
use App\Entity\SpicyType;
use Doctrine\ORM\EntityManagerInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\GoogleMultilangUrlDecorator;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SitemapSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, class-string>
     */
    private const array DETAIL_ROUTES = [
        'view_spice' => Spices::class,
        'view_aromatic_compound' => AromaticCompound::class,
        'view_alchemy_flavors' => AlchemyFlavors::class,
        'view_spicy_type' => SpicyType::class,
        'view_preparation_methods' => PreparationMethods::class,
        'view_aromatic_groups' => AromaticGroups::class,
    ];

    /**
     * @var list<string>
     */
    private const array STATIC_ROUTES = [
        'home',
        'index_spices',
        'index_aromatic_compound',
        'index_alchemy_flavors',
        'index_spicy_type',
        'index_preparation_methods',
        'index_aromatic_groups',
    ];

    private const string DEFAULT_LOCALE = 'fr';

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SitemapPopulateEvent::class => 'populate',
        ];
    }

    public function populate(SitemapPopulateEvent $event): void
    {
        $container = $event->getUrlContainer();

        foreach (self::STATIC_ROUTES as $route) {
            $this->addLocalizedUrls($container, $this->urlsFor($route, static fn (): array => []), null, 'pages');
        }

        foreach (self::DETAIL_ROUTES as $route => $class) {
            foreach ($this->em->getRepository($class)->findSitemapRows() as $row) {
                $urls = $this->urlsFor($route, static fn (string $locale): array => [
                    'slug' => $row['slugs'][$locale] ?? $row['slugs'][self::DEFAULT_LOCALE],
                ]);
                $this->addLocalizedUrls($container, $urls, $row['updatedAt'], 'content');
            }
        }
    }

    /**
     * @param \Closure(string): array<string, string> $params
     * @return array<string, string>
     */
    private function urlsFor(string $route, \Closure $params): array
    {
        $urls = [];
        foreach (LocaleSubscriber::SUPPORTED_LOCALES as $locale) {
            $urls[$locale] = $this->router->generate($route, [
                '_locale' => $locale,
            ] + $params($locale), UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $urls;
    }

    /**
     * @param array<string, string> $urls
     */
    private function addLocalizedUrls(
        UrlContainerInterface $container,
        array $urls,
        ?\DateTimeInterface $lastModified,
        string $section,
    ): void {
        foreach ($urls as $url) {
            $decorated = new GoogleMultilangUrlDecorator(new UrlConcrete($url, $lastModified));
            foreach ($urls as $locale => $alternate) {
                $decorated->addLink($alternate, $locale);
            }

            $decorated->addLink($urls[self::DEFAULT_LOCALE], 'x-default');
            $container->addUrl($decorated, $section);
        }
    }
}
