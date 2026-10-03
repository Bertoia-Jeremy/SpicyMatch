<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\SpicesRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpicesRepositoryFindSitePlanLinksTest extends KernelTestCase
{
    private Connection $connection;

    private SpicesRepository $spicesRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->spicesRepository = self::getContainer()->get(SpicesRepository::class);
    }

    public function testFrenchLinksAreSortedByNameAndSkipDeletedSpices(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->update('spices', [
                'deleted_at' => '2026-01-01 00:00:00',
            ], [
                'id' => $this->spiceId('cannelle'),
            ]);

            $slugs = array_column($this->spicesRepository->findSitePlanLinks('fr'), 'slug');

            self::assertNotContains('cannelle', $slugs);
            self::assertLessThan(array_search('curcuma', $slugs, true), array_search('cumin', $slugs, true));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testTranslatedLinksFallBackToFrenchOnMissingOrEmptyTranslation(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->insert('spice_translation', [
                'spice_id' => $this->spiceId('cumin'),
                'locale' => 'en',
                'name' => 'Aaa Plan Spice',
                'slug' => 'aaa-plan-spice',
                'reviewed' => 0,
            ]);
            $this->connection->insert('spice_translation', [
                'spice_id' => $this->spiceId('curcuma'),
                'locale' => 'en',
                'name' => '',
                'slug' => '',
                'reviewed' => 0,
            ]);

            $links = $this->spicesRepository->findSitePlanLinks('en');
            $bySlug = array_column($links, 'name', 'slug');

            self::assertSame([
                'name' => 'Aaa Plan Spice',
                'slug' => 'aaa-plan-spice',
            ], $links[0]);
            self::assertArrayNotHasKey('cumin', $bySlug);
            self::assertSame('Curcuma', $bySlug['curcuma'] ?? null);
            self::assertSame('Gingembre Séché', $bySlug['gingembre'] ?? null);
        } finally {
            $this->connection->rollBack();
        }
    }

    private function spiceId(string $slug): int
    {
        $id = (int) $this->connection->fetchOne('SELECT id FROM spices WHERE slug = ?', [$slug]);
        self::assertGreaterThan(0, $id, 'Base de test non seedée.');

        return $id;
    }
}
