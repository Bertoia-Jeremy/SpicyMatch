<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CanonicalSlugRedirectTest extends WebTestCase
{
    private const string EN_SLUG = 'canonical-test-en-slug';

    /**
     * @return iterable<string, array{string, int, string|null}>
     */
    public static function spiceUrlProvider(): iterable
    {
        yield 'canonical french slug' => ['/fr/epices/{fr}', 200, null];
        yield 'numeric legacy id' => ['/fr/epices/{id}', 301, '/fr/epices/{fr}'];
        yield 'foreign-locale slug' => ['/fr/epices/{en}', 301, '/fr/epices/{fr}'];
        yield 'french slug under translated locale' => ['/en/spices/{fr}', 301, '/en/spices/{en}'];
        yield 'unknown slug' => ['/fr/epices/no-such-spice-slug', 404, null];
    }

    #[DataProvider('spiceUrlProvider')]
    public function testSpiceUrlsResolveToTheCanonicalSlug(string $url, int $status, ?string $location): void
    {
        $client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        $spice = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Spices::class)->findOneBy([
            'deleted_at' => null,
        ], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($spice);
        $placeholders = [
            '{id}' => (string) $spice->getId(),
            '{fr}' => (string) $spice->getSlug(),
            '{en}' => self::EN_SLUG,
        ];
        $connection->beginTransaction();

        try {
            $connection->insert('spice_translation', [
                'spice_id' => $spice->getId(),
                'locale' => 'en',
                'name' => 'Canonical test spice',
                'slug' => self::EN_SLUG,
                'reviewed' => 0,
            ]);

            $client->request('GET', strtr($url, $placeholders));

            self::assertResponseStatusCodeSame($status);
            if ($location !== null) {
                self::assertResponseRedirects(strtr($location, $placeholders), 301);
            }
        } finally {
            $connection->rollBack();
        }
    }

    public function testNumericAromaticGroupFilterRedirectsToItsSlugKeepingOtherParameters(): void
    {
        $client = self::createClient();
        $group = self::getContainer()->get(EntityManagerInterface::class)->getRepository(AromaticGroups::class)->findOneBy([], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($group);

        $client->request('GET', '/fr/epices/?aromatic_group=' . $group->getId() . '&search=poivre');

        self::assertResponseRedirects('/fr/epices/?aromatic_group=' . $group->getSlug() . '&search=poivre', 301);
    }

    public function testCanonicalAromaticGroupFilterIsServedDirectly(): void
    {
        $client = self::createClient();
        $group = self::getContainer()->get(EntityManagerInterface::class)->getRepository(AromaticGroups::class)->findOneBy([], [
            'id' => 'ASC',
        ]);
        self::assertNotNull($group);

        $client->request('GET', '/fr/epices/?aromatic_group=' . $group->getSlug());

        self::assertResponseIsSuccessful();
    }
}
