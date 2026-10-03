<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpicesRepositoryFindByCompoundTest extends KernelTestCase
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

    public function testGroupsLiveSpicesByPlanePrimaryWinningAndSortsByLocalizedName(): void
    {
        $compound = $this->em->getRepository(AromaticCompound::class)
            ->findOneBy([], [
                'id' => 'ASC',
            ]);
        self::assertInstanceOf(AromaticCompound::class, $compound);
        $spices = $this->em->getRepository(Spices::class)
            ->findBy([
                'deleted_at' => null,
            ], [
                'id' => 'ASC',
            ]);
        $unlinked = array_values(array_filter(
            $spices,
            static fn (Spices $spice): bool => ! $spice->getAromaticsCompounds()
                ->contains($compound)
                && ! $spice->getSecondaryAromaticsCompounds()
                    ->contains($compound),
        ));
        self::assertGreaterThanOrEqual(3, \count($unlinked));
        [$both, $secondaryOnly, $deleted] = $unlinked;

        $both->addAromaticsCompounds($compound)
            ->addSecondaryAromaticsCompound($compound);
        $secondaryOnly->addSecondaryAromaticsCompound($compound);
        $deleted->addAromaticsCompounds($compound)
            ->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->em->clear();

        $compound = $this->em->find(AromaticCompound::class, $compound->getId());
        self::assertInstanceOf(AromaticCompound::class, $compound);

        $grouped = self::getContainer()->get(SpicesRepository::class)->findByCompound($compound, 'en');

        $primaryIds = array_map(static fn (Spices $spice): ?int => $spice->getId(), $grouped['primary']);
        $secondaryIds = array_map(static fn (Spices $spice): ?int => $spice->getId(), $grouped['secondary']);
        self::assertContains($both->getId(), $primaryIds);
        self::assertNotContains($both->getId(), $secondaryIds);
        self::assertContains($secondaryOnly->getId(), $secondaryIds);
        self::assertNotContains($deleted->getId(), [...$primaryIds, ...$secondaryIds]);
        self::assertSame(array_unique($primaryIds), $primaryIds);

        foreach ($grouped as $list) {
            $names = array_map(static fn (Spices $spice): string => (string) $spice->getLocalizedName('en'), $list);
            $sorted = $names;
            sort($sorted, SORT_STRING | SORT_FLAG_CASE);
            self::assertSame($sorted, $names);
        }
    }
}
