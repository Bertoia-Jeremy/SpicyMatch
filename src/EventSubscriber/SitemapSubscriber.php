<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
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

final class SitemapSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, class-string>
     */
    private const DETAIL_ROUTES = [
        'view_spice' => Spices::class,
        'view_aromatic_compound' => AromaticCompound::class,
        'view_alchemy_flavors' => AlchemyFlavors::class,
        'view_spicy_type' => SpicyType::class,
        'view_preparation_methods' => PreparationMethods::class,
    ];

    /**
     * @var list<string>
     */
    private const STATIC_ROUTES = [
        'home',
        'index_spices',
        'index_aromatic_compound',
        'index_alchemy_flavors',
        'index_spicy_type',
        'index_preparation_methods',
        'index_aromatic_groups',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
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
            $this->addMultilangUrl(
                $container,
                fn (string $locale): string => $this->router->generate(
                    $route,
                    [
                        '_locale' => $locale,
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'pages',
            );
        }

        foreach (self::DETAIL_ROUTES as $route => $class) {
            foreach ($this->em->getRepository($class)->findAll() as $entity) {
                if ($entity->getDeletedAt() !== null) {
                    continue;
                }

                $this->addMultilangUrl(
                    $container,
                    fn (string $locale): string => $this->router->generate(
                        $route,
                        [
                            '_locale' => $locale,
                            'slug' => $entity->getLocalizedSlug($locale),
                        ],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                    'content',
                );
            }
        }
    }

    /**
     * @param callable(string): string $urlFor
     */
    private function addMultilangUrl(UrlContainerInterface $container, callable $urlFor, string $section): void
    {
        $url = new GoogleMultilangUrlDecorator(new UrlConcrete($urlFor('fr')));

        foreach (LocaleSubscriber::SUPPORTED_LOCALES as $locale) {
            $url->addLink($urlFor($locale), $locale);
        }

        $container->addUrl($url, $section);
    }
}
