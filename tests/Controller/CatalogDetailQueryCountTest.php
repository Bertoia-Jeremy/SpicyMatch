<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\PreparationMethods;
use App\Entity\Spices;
use App\Entity\SpicyType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogDetailQueryCountTest extends WebTestCase
{
    /**
     * @param class-string $entity
     */
    #[DataProvider('pages')]
    public function testDetailPageStaysUnderItsQueryCeiling(string $entity, string $prefix, int $ceiling): void
    {
        $client = self::createClient();
        $subject = self::getContainer()->get(EntityManagerInterface::class)->getRepository($entity)->findOneBy([], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($subject);
        self::assertTrue(method_exists($subject, 'getLocalizedSlug'));

        $locale = substr($prefix, 1, 2);
        $url = $prefix . '/' . $subject->getLocalizedSlug($locale);
        $client->request('GET', $url);
        $client->enableProfiler();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertLessThanOrEqual($ceiling, $collector->getQueryCount());
    }

    /**
     * @return iterable<string, array{class-string, string, int}>
     */
    public static function pages(): iterable
    {
        yield 'method fr' => [PreparationMethods::class, '/fr/methodes-preparation', 4];
        yield 'method en' => [PreparationMethods::class, '/en/preparation-methods', 5];
        yield 'compound fr' => [AromaticCompound::class, '/fr/epices/composes-aromatiques', 7];
        yield 'compound en' => [AromaticCompound::class, '/en/spices/aromatic-compounds', 8];
        yield 'flavor fr' => [AlchemyFlavors::class, '/fr/epices/saveurs-aromatiques', 6];
        yield 'flavor en' => [AlchemyFlavors::class, '/en/spices/aromatic-flavors', 7];
        yield 'spice fr' => [Spices::class, '/fr/epices', 10];
        yield 'spice en' => [Spices::class, '/en/spices', 11];
        yield 'group fr' => [AromaticGroups::class, '/fr/epices/groupes-aromatiques', 7];
        yield 'group en' => [AromaticGroups::class, '/en/spices/aromatic-groups', 8];
        yield 'type fr' => [SpicyType::class, '/fr/epices/types-epices', 5];
        yield 'type en' => [SpicyType::class, '/en/spices/spice-types', 6];
    }
}
