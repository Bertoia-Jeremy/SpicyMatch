<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpiceDuo;
use App\Entity\SpiceDuoTranslation;
use App\Repository\SpiceDuoRepository;
use App\Tests\Support\IntegrationTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class SpiceDuoRepositoryTest extends IntegrationTestCase
{
    private SpiceDuoRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = static::getContainer()->get(SpiceDuoRepository::class);
    }

    public function testEmptySpiceIdsReturnEmpty(): void
    {
        self::assertSame([], $this->repo->findBySpiceIds([]));
    }

    public function testFindBySpiceIdsReturnsScalarRowsOrderedByRank(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cooks] = $this->spiceWithTips(2);
            $spiceId = (int) $prep->getSpice()?->getId();

            $this->persistDuo($prep, $cooks[0], 2, 'Second');
            $this->persistDuo($prep, $cooks[1], 1, 'Premier');
            $this->em->flush();

            $rows = $this->repo->findBySpiceIds([$spiceId]);

            self::assertCount(2, $rows);
            self::assertSame([1, 2], array_column($rows, 'rank'));
            self::assertSame(['Premier', 'Second'], array_column($rows, 'title'));
            self::assertSame($spiceId, $rows[0]['spiceId']);
            self::assertSame($prep->getId(), $rows[0]['prepId']);
            self::assertSame($cooks[1]->getId(), $rows[0]['cookId']);
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testFindBySpiceIdsCoalescesTranslationPerField(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cooks] = $this->spiceWithTips(1);
            $duo = $this->persistDuo($prep, $cooks[0], 1, 'Fond doré');
            $duo->addTranslation((new SpiceDuoTranslation())->setLocale('en')->setTitle('Golden base'));
            $this->em->flush();

            $spiceId = (int) $prep->getSpice()?->getId();
            $en = $this->repo->findBySpiceIds([$spiceId], 'en');
            $es = $this->repo->findBySpiceIds([$spiceId], 'es');
            $fr = $this->repo->findBySpiceIds([$spiceId], 'fr');

            self::assertSame('Golden base', $en[0]['title']);
            self::assertSame('effet', $en[0]['effect']);
            self::assertSame('Fond doré', $es[0]['title']);
            self::assertSame('Fond doré', $fr[0]['title']);
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testFindByTipIdsReturnsOnlyChosenPairs(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cooks] = $this->spiceWithTips(2);
            $this->persistDuo($prep, $cooks[0], 1, 'Retenu');
            $this->persistDuo($prep, $cooks[1], 2, 'Ecarte');
            $this->em->flush();

            $rows = $this->repo->findByTipIds([(int) $prep->getId()], [(int) $cooks[0]->getId()]);

            self::assertCount(1, $rows);
            self::assertSame('Retenu', $rows[0]['title']);
            self::assertSame([], $this->repo->findByTipIds([], [(int) $cooks[0]->getId()]));
            self::assertSame([], $this->repo->findByTipIds([(int) $prep->getId()], []));
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    public function testValidatorRejectsTipsFromDifferentSpices(): void
    {
        $preps = $this->em->getRepository(PreparationTips::class)->findAll();
        $prep = $preps[0];
        $other = null;
        foreach ($this->em->getRepository(CookingTips::class)->findAll() as $cook) {
            if ($cook->getSpice() !== $prep->getSpice()) {
                $other = $cook;
                break;
            }
        }
        self::assertNotNull($other);

        $duo = (new SpiceDuo())
            ->setPreparationTip($prep)
            ->setCookingTip($other)
            ->setTitle('t')
            ->setEffect('e')
            ->setScience('s')
            ->setExample('x');

        $violations = static::getContainer()->get('validator')->validate($duo);

        self::assertCount(1, $violations);
        self::assertSame('cookingTip', $violations[0]->getPropertyPath());
    }

    public function testUniqueConstraintRejectsDuplicatePair(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            [$prep, $cooks] = $this->spiceWithTips(1);
            $this->persistDuo($prep, $cooks[0], 1, 'A');
            $this->em->flush();
            $this->persistDuo($prep, $cooks[0], 2, 'B');

            $this->expectException(UniqueConstraintViolationException::class);
            $this->em->flush();
        } finally {
            $this->em->clear();
            $connection->rollBack();
        }
    }

    /**
     * @return array{PreparationTips, list<CookingTips>}
     */
    private function spiceWithTips(int $cookingCount): array
    {
        foreach ($this->em->getRepository(PreparationTips::class)->findAll() as $prep) {
            $spice = $prep->getSpice();
            if ($spice === null) {
                continue;
            }
            $cooks = $this->em->getRepository(CookingTips::class)->findBy([
                'spice' => $spice,
            ]);
            if (\count($cooks) >= $cookingCount) {
                return [$prep, \array_slice($cooks, 0, $cookingCount)];
            }
        }

        self::markTestSkipped('Aucune épice avec conseil de préparation et assez de conseils de cuisson.');
    }

    private function persistDuo(PreparationTips $prep, CookingTips $cook, int $rank, string $title): SpiceDuo
    {
        $duo = (new SpiceDuo())
            ->setPreparationTip($prep)
            ->setCookingTip($cook)
            ->setRank($rank)
            ->setTitle($title)
            ->setEffect('effet')
            ->setScience('science')
            ->setExample('exemple')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($duo);

        return $duo;
    }
}
