<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\AromaticCompound;
use App\Entity\SpiceTranslation;
use App\Entity\Users;
use App\EventListener\SitemapCacheInvalidator;
use App\EventSubscriber\SitemapSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SitemapCacheInvalidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{object, bool}>
     */
    public static function entityProvider(): iterable
    {
        yield 'catalogue entity' => [new AromaticCompound(), false];
        yield 'catalogue translation' => [new SpiceTranslation(), false];
        yield 'unrelated entity' => [new Users(), true];
    }

    #[DataProvider('entityProvider')]
    public function testOnlyCatalogueWritesDropTheCachedRows(object $entity, bool $kept): void
    {
        $cache = new ArrayAdapter();
        $cache->get(SitemapSubscriber::CACHE_KEY, static fn (): array => []);

        new SitemapCacheInvalidator($cache)
            ->postUpdate(new PostUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class)));

        self::assertSame($kept, $cache->hasItem(SitemapSubscriber::CACHE_KEY));
    }
}
