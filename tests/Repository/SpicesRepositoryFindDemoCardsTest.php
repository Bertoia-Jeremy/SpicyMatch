<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\SpicesRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpicesRepositoryFindDemoCardsTest extends KernelTestCase
{
    private Connection $connection;

    private SpicesRepository $spicesRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->spicesRepository = self::getContainer()->get(SpicesRepository::class);
    }

    public function testCardsFollowRequestedOrderAndSkipUnknownSlugs(): void
    {
        $cards = $this->spicesRepository->findDemoCards(['curcuma', 'does-not-exist', 'cumin'], 'fr');

        self::assertSame(['curcuma', 'cumin'], array_column($cards, 'slug'));
    }

    public function testCardsUseLocalizedNameAndSlugWithFrenchFallback(): void
    {
        $this->connection->beginTransaction();

        try {
            $cuminId = (int) $this->connection->fetchOne('SELECT id FROM spices WHERE slug = ?', ['cumin']);
            self::assertGreaterThan(0, $cuminId, 'Base de test non seedée.');
            $this->connection->insert('spice_translation', [
                'spice_id' => $cuminId,
                'locale' => 'es',
                'name' => 'Comino',
                'slug' => 'comino',
                'reviewed' => 0,
            ]);

            $cards = $this->spicesRepository->findDemoCards(['cumin', 'curcuma'], 'es');

            self::assertSame(['Comino', 'comino'], [$cards[0]['name'], $cards[0]['slug']]);
            self::assertSame('curcuma', $cards[1]['slug']);
        } finally {
            $this->connection->rollBack();
        }
    }
}
