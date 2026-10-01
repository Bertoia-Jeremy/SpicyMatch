<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\SpicesRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpicesRepositoryFindAllSpicesTest extends KernelTestCase
{
    private Connection $connection;

    private SpicesRepository $spicesRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->spicesRepository = self::getContainer()->get(SpicesRepository::class);
    }

    public function testLocalizedNamesFallBackToFrenchWhenUntranslated(): void
    {
        $this->connection->beginTransaction();

        try {
            $french = $this->namesById($this->spicesRepository->findAllSpices('fr'));
            self::assertGreaterThanOrEqual(2, \count($french), 'Base de test non seedée.');
            [$translatedId, $untranslatedId] = array_slice(array_keys($french), 0, 2);
            $this->connection->insert('spice_translation', [
                'spice_id' => $translatedId,
                'locale' => 'en',
                'name' => 'Translated spice',
                'reviewed' => 0,
            ]);

            $english = $this->namesById($this->spicesRepository->findAllSpices('en'));

            self::assertSame('Translated spice', $english[$translatedId]);
            self::assertSame($french[$untranslatedId], $english[$untranslatedId]);
            self::assertSame(array_keys($french), array_keys($this->namesById($this->spicesRepository->findAllSpices())));
            self::assertCount(\count($french), $english);
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * @param array<array<string, mixed>> $rows
     *
     * @return array<int, string>
     */
    private function namesById(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }
}
