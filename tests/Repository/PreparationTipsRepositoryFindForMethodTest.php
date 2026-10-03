<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\PreparationMethods;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Repository\PreparationTipsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PreparationTipsRepositoryFindForMethodTest extends KernelTestCase
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

    public function testReturnsLiveTipsOfTheMethodSortedBySpiceName(): void
    {
        $method = $this->em->getRepository(PreparationMethods::class)
            ->findOneBy([], [
                'id' => 'ASC',
            ]);
        self::assertInstanceOf(PreparationMethods::class, $method);
        $spices = $this->em->getRepository(Spices::class)
            ->findBy([
                'deleted_at' => null,
            ], [
                'id' => 'ASC',
            ], 2);
        self::assertCount(2, $spices);

        $deletedTip = $this->tip($method, $spices[0])
            ->setDeletedAt(new \DateTimeImmutable());
        $spices[1]->setDeletedAt(new \DateTimeImmutable());
        $tipOnDeletedSpice = $this->tip($method, $spices[1]);
        $this->em->flush();
        $this->em->clear();

        $method = $this->em->find(PreparationMethods::class, $method->getId());
        self::assertInstanceOf(PreparationMethods::class, $method);

        $tips = self::getContainer()->get(PreparationTipsRepository::class)->findForMethod($method, 'en');

        $ids = array_map(static fn (PreparationTips $tip): ?int => $tip->getId(), $tips);
        self::assertNotEmpty($tips);
        self::assertNotContains($deletedTip->getId(), $ids);
        self::assertNotContains($tipOnDeletedSpice->getId(), $ids);
        foreach ($tips as $tip) {
            self::assertSame($method->getId(), $tip->getPreparationMethod()?->getId());
        }
        $names = array_map(static fn (PreparationTips $tip): string => (string) $tip->getSpice()?->getLocalizedName('en'), $tips);
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        self::assertSame($sorted, $names);
    }

    private function tip(PreparationMethods $method, Spices $spice): PreparationTips
    {
        $tip = new PreparationTips()
            ->setText('Texte')
            ->setAdvantages('Atout')
            ->setSpice($spice)
            ->setPreparationMethod($method)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($tip);

        return $tip;
    }
}
