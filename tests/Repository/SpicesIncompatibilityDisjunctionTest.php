<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Spices;
use App\Repository\CandidateVetoRepository;
use App\Repository\SpicesRepository;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpicesIncompatibilityDisjunctionTest extends KernelTestCase
{
    private const int SAMPLED_BASES = 8;

    private Connection $connection;
    private SpicesRepository $spicesRepository;
    private CandidateVetoRepository $vetoRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->spicesRepository = self::getContainer()->get(SpicesRepository::class);
        $this->vetoRepository = self::getContainer()->get(CandidateVetoRepository::class);
    }

    public function testIncompatibleSpicesNeverSurviveThePresenceVeto(): void
    {
        $this->connection->beginTransaction();

        try {
            $bases = $this->spicesRepository->findBy([], [
                'id' => 'ASC',
            ], self::SAMPLED_BASES);

            self::assertNotEmpty($bases, 'Base de test non seedée : aucune épice disponible.');

            foreach ($bases as $base) {
                $baseId = $base->getId();

                if (null === $baseId) {
                    continue;
                }

                $incompatibleIds = array_map(
                    static fn (Spices $spice) => (int) $spice->getId(),
                    $this->spicesRepository->findIncompatibleWith($base),
                );
                $survivorIds = $this->vetoRepository->findSurvivorsWithPresence(new MortarIds([$baseId]));

                self::assertSame(
                    [],
                    array_values(array_intersect($incompatibleIds, $survivorIds)),
                    'Épice '.$baseId.' : intrus et survivants du veto se recoupent.',
                );
            }
        } finally {
            $this->connection->rollBack();
        }
    }
}
