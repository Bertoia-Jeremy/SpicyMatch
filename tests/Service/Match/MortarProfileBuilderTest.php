<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpiceActiveCompoundRepository;
use App\Service\Match\MortarProfileBuilder;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(MortarProfileBuilder::class)]
final class MortarProfileBuilderTest extends TestCase
{
    private function coldCache(): CacheItemPoolInterface
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(false);
        $item->method('set')
            ->willReturn($item);
        $item->method('expiresAfter')
            ->willReturn($item);

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturn($item);
        $pool->method('save')
            ->willReturn(true);

        return $pool;
    }

    /**
     * @param array<int, float> $cachedValue
     */
    private function warmCache(array $cachedValue): CacheItemPoolInterface
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(true);
        $item->method('get')
            ->willReturn($cachedValue);

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturn($item);

        return $pool;
    }

    public function testBuildComputesMaxPerCompound(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                1 => [
                    10 => 5.0,
                    20 => 2.0,
                ],
                2 => [
                    10 => 3.0,
                    30 => 8.0,
                ],
            ]);

        $builder = new MortarProfileBuilder($repo, $this->coldCache());
        $profile = $builder->build(new MortarIds([1, 2]), OdtMatrix::AIR);

        self::assertSame(5.0, $profile[10], 'eugenol : max(5.0, 3.0) = 5.0');
        self::assertSame(2.0, $profile[20], 'thymol : max(2.0, 0) = 2.0');
        self::assertSame(8.0, $profile[30], 'carvacrol : max(0, 8.0) = 8.0');
    }

    public function testBuildSingleSpiceReturnsItsOwnProfile(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                5 => [
                    10 => 3.5,
                    20 => 1.2,
                ],
            ]);

        $builder = new MortarProfileBuilder($repo, $this->coldCache());
        $profile = $builder->build(new MortarIds([5]), OdtMatrix::AIR);

        self::assertSame(3.5, $profile[10]);
        self::assertSame(1.2, $profile[20]);
    }

    public function testBuildEmptyOavDataReturnsNull(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([]);

        $builder = new MortarProfileBuilder($repo, $this->coldCache());
        $profile = $builder->build(new MortarIds([1, 2]), OdtMatrix::AIR);

        self::assertNull($profile);
    }

    public function testEmptyOavStateIsCachedAndShortCircuitsSubsequentBuilds(): void
    {
        $repo = $this->createMock(SpiceActiveCompoundRepository::class);
        $repo->expects(self::once())
            ->method('loadOavProfilesBatch')
            ->willReturn([]);

        $builder = new MortarProfileBuilder($repo, new \Symfony\Component\Cache\Adapter\ArrayAdapter());

        self::assertNull($builder->build(new MortarIds([1]), OdtMatrix::AIR));
        self::assertNull($builder->build(new MortarIds([1]), OdtMatrix::AIR));
    }

    public function testBuildUsesCacheOnHit(): void
    {
        $cached = [
            10 => 9.9,
            20 => 4.4,
        ];

        $repo = $this->createMock(SpiceActiveCompoundRepository::class);
        $repo->expects(self::never())->method('loadOavProfilesBatch');

        $builder = new MortarProfileBuilder($repo, $this->warmCache($cached));
        $profile = $builder->build(new MortarIds([1, 2]), OdtMatrix::AIR);

        self::assertSame($cached, $profile);
    }

    public function testBuildSortsIdsForCacheKey(): void
    {
        $cacheKey = null;

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(false);
        $item->method('set')
            ->willReturn($item);
        $item->method('expiresAfter')
            ->willReturn($item);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturnCallback(function (string $key) use (&$cacheKey, $item): CacheItemInterface {
                $cacheKey = $key;

                return $item;
            });
        $pool->method('save')
            ->willReturn(true);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([]);

        $builder = new MortarProfileBuilder($repo, $pool);

        $builder->build(new MortarIds([3, 1, 2]), OdtMatrix::AIR);
        $keyA = $cacheKey;

        $builder->build(new MortarIds([1, 2, 3]), OdtMatrix::AIR);
        $keyB = $cacheKey;

        self::assertSame($keyA, $keyB, 'Ordre des IDs ne doit pas affecter la clé de cache');
    }

    public function testCacheKeyIncludesMatrix(): void
    {
        $capturedKeys = [];

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(false);
        $item->method('set')
            ->willReturn($item);
        $item->method('expiresAfter')
            ->willReturn($item);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturnCallback(function (string $key) use (&$capturedKeys, $item): CacheItemInterface {
                $capturedKeys[] = $key;

                return $item;
            });
        $pool->method('save')
            ->willReturn(true);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([]);

        $builder = new MortarProfileBuilder($repo, $pool);
        $mortar = new MortarIds([1, 2]);

        $builder->build($mortar, OdtMatrix::AIR);
        $builder->build($mortar, OdtMatrix::WATER);
        $builder->build($mortar, OdtMatrix::OIL);

        self::assertCount(3, array_unique($capturedKeys), '3 matrices → 3 clés de cache distinctes');
        self::assertStringContainsString('air', $capturedKeys[0]);
        self::assertStringContainsString('water', $capturedKeys[1]);
        self::assertStringContainsString('oil', $capturedKeys[2]);
    }

    public function testAirMatrixUses24hTtl(): void
    {
        $capturedTtl = null;

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(false);
        $item->method('set')
            ->willReturn($item);
        $item->method('expiresAfter')
            ->willReturnCallback(function (int $ttl) use (&$capturedTtl, $item): CacheItemInterface {
                $capturedTtl = $ttl;

                return $item;
            });

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturn($item);
        $pool->method('save')
            ->willReturn(true);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                1 => [
                    10 => 5.0,
                ],
            ]);

        $builder = new MortarProfileBuilder($repo, $pool);
        $builder->build(new MortarIds([1]), OdtMatrix::AIR);

        self::assertSame(86400, $capturedTtl, 'air → TTL 24h = 86400s');
    }

    public function testWaterMatrixUsesShortTtl(): void
    {
        $capturedTtl = null;

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(false);
        $item->method('set')
            ->willReturn($item);
        $item->method('expiresAfter')
            ->willReturnCallback(function (int $ttl) use (&$capturedTtl, $item): CacheItemInterface {
                $capturedTtl = $ttl;

                return $item;
            });

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturn($item);
        $pool->method('save')
            ->willReturn(true);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                1 => [
                    10 => 3.0,
                ],
            ]);

        $builder = new MortarProfileBuilder($repo, $pool);
        $builder->build(new MortarIds([1]), OdtMatrix::WATER);

        self::assertSame(3600, $capturedTtl, 'water → TTL 1h = 3600s (données moins matures)');
    }

    public function testOilMatrixUsesShortTtl(): void
    {
        $capturedTtl = null;

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(false);
        $item->method('set')
            ->willReturn($item);
        $item->method('expiresAfter')
            ->willReturnCallback(function (int $ttl) use (&$capturedTtl, $item): CacheItemInterface {
                $capturedTtl = $ttl;

                return $item;
            });

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')
            ->willReturn($item);
        $pool->method('save')
            ->willReturn(true);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                1 => [
                    10 => 2.5,
                ],
            ]);

        $builder = new MortarProfileBuilder($repo, $pool);
        $builder->build(new MortarIds([1]), OdtMatrix::OIL);

        self::assertSame(3600, $capturedTtl, 'oil → TTL 1h = 3600s');
    }
}
