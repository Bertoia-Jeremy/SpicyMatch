<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Enum\CookingMoment;
use App\Service\Data\SpiceDuoImporter;
use App\Tests\Support\IntegrationTestCase;
use App\ValueObject\SpiceDuoRow;

final class SpiceDuoImporterTest extends IntegrationTestCase
{
    private SpiceDuoImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = self::getContainer()->get(SpiceDuoImporter::class);
    }

    public function testUpsertCreatesThenUpdatesWithoutDuplicate(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cook] = $this->pair();

            self::assertTrue($this->importer->upsert($this->row($prep, $cook, 1, 'Premier')));
            self::assertFalse($this->importer->upsert($this->row($prep, $cook, 2, 'Second')));

            $duos = $this->em->getRepository(SpiceDuo::class)->findBy([
                'preparationTip' => $prep,
                'cookingTip' => $cook,
            ]);
            self::assertCount(1, $duos);
            self::assertSame('Second', $duos[0]->getTitle());
            self::assertSame(2, $duos[0]->getRank());
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testMissingCookingTipIsAnExplicitError(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cook] = $this->pair();
            $this->em->remove($cook);
            $this->em->flush();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Aucun moment');

            $this->importer->upsert($this->row($prep, $cook, 1, 'X'));
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testSoftDeletedCookingTipIsIgnored(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cook] = $this->pair();
            $cook->setDeletedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('Aucun moment');

            $this->importer->upsert($this->row($prep, $cook, 1, 'X'));
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testDuplicateCookingTipOnSameMomentIsAmbiguous(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cook] = $this->pair();
            $twin = new CookingTips()
                ->setSpice($cook->getSpice())
                ->setMoment($cook->getMoment())
                ->setText('doublon')
                ->setAdvantages('doublon')
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());
            $this->em->persist($twin);
            $this->em->flush();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('ambigu');

            $this->importer->upsert($this->row($prep, $cook, 1, 'X'));
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testTitleLongerThan255IsRejected(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cook] = $this->pair();

            $this->expectException(\RuntimeException::class);

            $this->importer->upsert($this->row($prep, $cook, 1, str_repeat('a', 256)));
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testUnknownSpiceIsAnExplicitError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('introuvable');

        $this->importer->upsert(new SpiceDuoRow('epice-inexistante', 'infusion', CookingMoment::START, 1, 't', 'e', 's', 'x'));
    }

    /**
     * @return array{PreparationTips, CookingTips}
     */
    private function pair(): array
    {
        foreach ($this->em->getRepository(PreparationTips::class)->findAll() as $prep) {
            $spice = $prep->getSpice();
            $method = $prep->getPreparationMethod();
            if ($spice === null || $method === null) {
                continue;
            }
            $cook = $this->em->getRepository(CookingTips::class)->findOneBy([
                'spice' => $spice,
            ]);
            if ($cook instanceof CookingTips) {
                $spice->setSlug($spice->getSlug() ?? 'test-spice-' . $spice->getId());
                $method->setSlug($method->getSlug() ?? 'test-method-' . $method->getId());
                $this->em->flush();

                return [$prep, $cook];
            }
        }

        self::markTestSkipped('Aucune épice avec préparation et moment.');
    }

    private function row(PreparationTips $prep, CookingTips $cook, int $rank, string $title): SpiceDuoRow
    {
        return new SpiceDuoRow(
            (string) $prep->getSpice()?->getSlug(),
            (string) $prep->getPreparationMethod()?->getSlug(),
            $cook->getMoment(),
            $rank,
            $title,
            'effet',
            'science',
            'exemple',
        );
    }
}
