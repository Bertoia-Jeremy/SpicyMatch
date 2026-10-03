<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Repository\AromaticCompoundRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AromaticCompoundRepositoryFindSignatureForGroupTest extends KernelTestCase
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

    public function testRanksPrimaryCompoundsByLiveMembersCarryingThem(): void
    {
        $group = $this->richestGroup();
        $members = $this->em->getRepository(Spices::class)
            ->findBy([
                'aromaticGroups' => $group,
                'deleted_at' => null,
            ], [
                'id' => 'ASC',
            ]);
        $members[0]->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        $expected = [];
        foreach (\array_slice($members, 1) as $spice) {
            foreach ($spice->getAromaticsCompounds() as $compound) {
                if ($compound->getDeletedAt() === null) {
                    $expected[$compound->getId()] = ($expected[$compound->getId()] ?? 0) + 1;
                }
            }
        }
        $this->em->clear();

        $group = $this->em->find(AromaticGroups::class, $group->getId());
        self::assertInstanceOf(AromaticGroups::class, $group);
        $signature = self::getContainer()->get(AromaticCompoundRepository::class)->findSignatureForGroup($group, 'en', 4);

        self::assertNotEmpty($signature);
        self::assertLessThanOrEqual(4, \count($signature));
        $counts = array_map(static fn (array $row): int => $row['carriers'], $signature);
        $sorted = $counts;
        rsort($sorted);
        self::assertSame($sorted, $counts);
        self::assertSame(max($expected), $counts[0]);
        foreach ($signature as $row) {
            self::assertInstanceOf(AromaticCompound::class, $row['compound']);
            self::assertSame($expected[$row['compound']->getId()] ?? 0, $row['carriers']);
        }
    }

    public function testGroupWhoseMembersAreAllDeletedHasNoSignature(): void
    {
        $group = $this->richestGroup();
        foreach ($this->em->getRepository(Spices::class)->findBy([
            'aromaticGroups' => $group,
        ]) as $spice) {
            $spice->setDeletedAt(new \DateTimeImmutable());
        }
        $this->em->flush();

        self::assertSame([], self::getContainer()->get(AromaticCompoundRepository::class)->findSignatureForGroup($group, 'fr'));
    }

    private function richestGroup(): AromaticGroups
    {
        $best = null;
        $bestCount = 0;
        foreach ($this->em->getRepository(AromaticGroups::class)->findAll() as $group) {
            $count = $this->em->getRepository(Spices::class)
                ->count([
                    'aromaticGroups' => $group,
                    'deleted_at' => null,
                ]);
            if ($count > $bestCount) {
                $best = $group;
                $bestCount = $count;
            }
        }
        self::assertInstanceOf(AromaticGroups::class, $best);
        self::assertGreaterThanOrEqual(3, $bestCount);

        return $best;
    }
}
