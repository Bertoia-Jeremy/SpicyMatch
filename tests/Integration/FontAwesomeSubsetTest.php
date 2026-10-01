<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\Icon\IconSubsetManifest;
use App\Service\Icon\IconUsageCollector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FontAwesomeSubsetTest extends KernelTestCase
{
    public function testEveryUsedIconTokenIsCoveredByTheSubset(): void
    {
        self::bootKernel();
        $collector = self::getContainer()->get(IconUsageCollector::class);
        $manifest = self::getContainer()->get(IconSubsetManifest::class);
        self::assertInstanceOf(IconUsageCollector::class, $collector);
        self::assertInstanceOf(IconSubsetManifest::class, $manifest);

        $missing = array_values(array_diff($collector->collect(), $manifest->knownTokens()));

        self::assertSame([], $missing, 'Relancer : php bin/console app:icons:list && yarn icons:subset');
    }
}
