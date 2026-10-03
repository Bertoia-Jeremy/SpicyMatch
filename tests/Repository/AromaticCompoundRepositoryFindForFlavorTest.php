<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Repository\AromaticCompoundRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AromaticCompoundRepositoryFindForFlavorTest extends KernelTestCase
{
    public function testReturnsLiveCompoundsOfTheFlavorSortedByLocalizedName(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $flavor = $em->getRepository(AlchemyFlavors::class)
                ->findOneBy([], [
                    'id' => 'ASC',
                ]);
            self::assertInstanceOf(AlchemyFlavors::class, $flavor);
            $outsiders = array_values(array_filter(
                $em->getRepository(AromaticCompound::class)->findBy([
                    'deleted_at' => null,
                ], [
                    'id' => 'ASC',
                ]),
                static fn (AromaticCompound $c): bool => ! $c->getAlchemyFlavors()
                    ->contains($flavor),
            ));
            self::assertGreaterThanOrEqual(2, \count($outsiders));
            [$added, $deleted] = $outsiders;
            $expected = array_map(
                static fn (AromaticCompound $c): ?int => $c->getId(),
                [...$flavor->getAromaticsCompounds()->filter(static fn (AromaticCompound $c): bool => $c->getDeletedAt() === null), $added],
            );
            $added->addAlchemyFlavors($flavor);
            $deleted->addAlchemyFlavors($flavor)
                ->setDeletedAt(new \DateTimeImmutable());
            $em->flush();
            $em->clear();

            $flavor = $em->find(AlchemyFlavors::class, $flavor->getId());
            self::assertInstanceOf(AlchemyFlavors::class, $flavor);
            $compounds = self::getContainer()->get(AromaticCompoundRepository::class)->findForFlavor($flavor, 'en');
        } finally {
            $connection->rollBack();
        }

        $ids = array_map(static fn (AromaticCompound $c): ?int => $c->getId(), $compounds);
        self::assertEqualsCanonicalizing($expected, $ids);
        self::assertNotContains($deleted->getId(), $ids);

        $names = array_map(static fn (AromaticCompound $c): string => (string) $c->getLocalizedName('en'), $compounds);
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        self::assertSame($sorted, $names);
    }
}
