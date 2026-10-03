<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpicesRepositoryFindByCompoundIdsTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testCountsLeadingAndSharedCompoundsAndRanksStrongestCarriersFirst(): void
    {
        [$first, $second] = $this->em->getRepository(AromaticCompound::class)
            ->findBy([], [
                'id' => 'ASC',
            ], 2);
        $unlinked = array_values(array_filter(
            $this->em->getRepository(Spices::class)->findBy([
                'deleted_at' => null,
            ], [
                'id' => 'ASC',
            ]),
            static fn (Spices $spice): bool => array_all(
                [$first, $second],
                static fn (AromaticCompound $c): bool => ! $spice->getAromaticsCompounds()
                    ->contains($c)
                    && ! $spice->getSecondaryAromaticsCompounds()
                        ->contains($c),
            ),
        ));
        self::assertGreaterThanOrEqual(3, \count($unlinked));
        [$leading, $background, $deleted] = $unlinked;

        $leading->addAromaticsCompounds($first)
            ->addSecondaryAromaticsCompound($second);
        $background->addSecondaryAromaticsCompound($first);
        $deleted->addAromaticsCompounds($first)
            ->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->em->clear();

        $rows = self::getContainer()->get(SpicesRepository::class)
            ->findByCompoundIds([(int) $first->getId(), (int) $second->getId()], 'en');

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['spice']->getId()] = $row;
        }
        self::assertCount(\count($rows), $byId);
        self::assertSame([1, 2], [$byId[$leading->getId()]['primary'], $byId[$leading->getId()]['shared']]);
        self::assertSame([0, 1], [$byId[$background->getId()]['primary'], $byId[$background->getId()]['shared']]);
        self::assertArrayNotHasKey((int) $deleted->getId(), $byId);
        self::assertSame([
            [
                'id' => (int) $first->getId(),
                'primary' => true,
            ],
            [
                'id' => (int) $second->getId(),
                'primary' => false,
            ],
        ], $byId[$leading->getId()]['links']);
        self::assertSame([[
            'id' => (int) $first->getId(),
            'primary' => false,
        ]], $byId[$background->getId()]['links']);

        $ranks = array_map(static fn (array $row): array => [$row['primary'], $row['shared']], $rows);
        $sorted = $ranks;
        rsort($sorted);
        self::assertSame($sorted, $ranks);
    }

    public function testNoCompoundMeansNoCarrier(): void
    {
        self::assertSame([], self::getContainer()->get(SpicesRepository::class)->findByCompoundIds([], 'fr'));
    }
}
