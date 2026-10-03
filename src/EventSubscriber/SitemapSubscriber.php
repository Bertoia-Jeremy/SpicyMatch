<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\HelpController;
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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class SitemapSubscriber implements EventSubscriberInterface
{
    public const string CACHE_KEY = 'sitemap.detail_rows';

    /**
     * @var array<string, class-string>
     */
    public const array DETAIL_ROUTES = [
        'view_spice' => Spices::class,
        'view_aromatic_compound' => AromaticCompound::class,
        'view_alchemy_flavors' => AlchemyFlavors::class,
        'view_spicy_type' => SpicyType::class,
        'view_preparation_methods' => PreparationMethods::class,
        'view_aromatic_groups' => AromaticGroups::class,
    ];

    /**
     * @var array<string, array{string, float}>
     */
    public const array STATIC_ROUTES = [
        'home' => [UrlConcrete::CHANGEFREQ_WEEKLY, 1.0],
        'index_spicy_match' => [UrlConcrete::CHANGEFREQ_MONTHLY, 0.9],
        'index_spices' => [UrlConcrete::CHANGEFREQ_WEEKLY, 0.9],
        'index_aromatic_compound' => [UrlConcrete::CHANGEFREQ_WEEKLY, 0.8],
        'index_alchemy_flavors' => [UrlConcrete::CHANGEFREQ_WEEKLY, 0.8],
        'index_spicy_type' => [UrlConcrete::CHANGEFREQ_WEEKLY, 0.8],
        'index_preparation_methods' => [UrlConcrete::CHANGEFREQ_WEEKLY, 0.8],
        'index_aromatic_groups' => [UrlConcrete::CHANGEFREQ_WEEKLY, 0.8],
        'faq_index' => [UrlConcrete::CHANGEFREQ_MONTHLY, 0.6],
        'help_index' => [UrlConcrete::CHANGEFREQ_MONTHLY, 0.4],
        'legal_notice' => [UrlConcrete::CHANGEFREQ_YEARLY, 0.2],
        'privacy_policy' => [UrlConcrete::CHANGEFREQ_YEARLY, 0.2],
        'accessibility_declaration' => [UrlConcrete::CHANGEFREQ_YEARLY, 0.2],
        'site_plan' => [UrlConcrete::CHANGEFREQ_MONTHLY, 0.3],
    ];

    private const string DETAIL_CHANGEFREQ = UrlConcrete::CHANGEFREQ_MONTHLY;

    private const float DETAIL_PRIORITY = 0.7;

    private const string DEFAULT_LOCALE = 'fr';

    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $router,
        #[Target('sitemap.cache')]
        private CacheInterface $cache,
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

        foreach (self::STATIC_ROUTES as $route => [$changefreq, $priority]) {
            $urls = $this->urlsFor($route, static fn (): array => []);
            $this->addLocalizedUrls($container, $urls, null, $changefreq, $priority, 'pages');
        }

        foreach (array_keys(HelpController::KNOWN_TOPICS) as $topic) {
            $urls = $this->urlsFor('help_topic', static fn (): array => [
                'topic' => $topic,
            ]);
            $this->addLocalizedUrls($container, $urls, null, UrlConcrete::CHANGEFREQ_MONTHLY, 0.3, 'pages');
        }

        foreach ($this->detailRows() as $route => $rows) {
            foreach ($rows as $row) {
                $urls = $this->urlsFor($route, static fn (string $locale): array => [
                    'slug' => $row['slugs'][$locale] ?? $row['slugs'][self::DEFAULT_LOCALE],
                ]);
                $this->addLocalizedUrls($container, $urls, $row['updatedAt'], self::DETAIL_CHANGEFREQ, self::DETAIL_PRIORITY, 'content');
            }
        }
    }

    /**
     * @return array<string, list<array{slugs: array<string, string>, updatedAt: \DateTimeInterface|null}>>
     */
    private function detailRows(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (): array {
            $rows = [];
            foreach (self::DETAIL_ROUTES as $route => $class) {
                $rows[$route] = $this->em->getRepository($class)->findSitemapRows();
            }

            return $rows;
        });
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
        string $changefreq,
        float $priority,
        string $section,
    ): void {
        foreach ($urls as $url) {
            $decorated = new GoogleMultilangUrlDecorator(new UrlConcrete($url, $lastModified, $changefreq, $priority));
            foreach ($urls as $locale => $alternate) {
                $decorated->addLink($alternate, $locale);
            }

            $decorated->addLink($urls[self::DEFAULT_LOCALE], 'x-default');
            $container->addUrl($decorated, $section);
        }
    }
}
