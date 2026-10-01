<?php

declare(strict_types=1);

namespace App\Tests\Service\Recipe;

use App\Entity\AromaticGroups;
use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Enum\CookingMoment;
use App\Repository\SpiceDuoRepository;
use App\Service\Recipe\RecipeStepsBuilder;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Recipe\RecipeSpiceStep;
use App\ValueObject\Recipe\RecipeView;
use PHPUnit\Framework\TestCase;

final class RecipeStepsBuilderTest extends TestCase
{
    public function testStepsAreSortedByMomentThenMortarOrder(): void
    {
        $a = $this->spice(1);
        $b = $this->spice(2);
        $c = $this->spice(3);
        $history = $this->history([$a, $b, $c]);
        $this->withCooking($history, $a, 10, CookingMoment::PLATING);
        $this->withCooking($history, $b, 11, CookingMoment::PRE);
        $this->withCooking($history, $c, 12, CookingMoment::PLATING);

        $steps = $this->builder()
            ->build($history, 'fr');

        self::assertSame([2, 1, 3], array_map(static fn (RecipeSpiceStep $s): int => $s->spiceId(), $steps));
    }

    public function testPreparationWithoutCookingFallsBackToPre(): void
    {
        $spice = $this->spice(1);
        $history = $this->history([$spice]);
        $this->withPreparation($history, $spice, 20);

        $steps = $this->builder()
            ->build($history, 'fr');

        self::assertCount(1, $steps);
        self::assertSame(CookingMoment::PRE, $steps[0]->moment);
        self::assertNull($steps[0]->cooking);
    }

    public function testSoftDeletedTipsAreIgnored(): void
    {
        $spice = $this->spice(1);
        $history = $this->history([$spice]);
        $this->withPreparation($history, $spice, 20)
            ->setDeletedAt(new \DateTimeImmutable());
        $this->withCooking($history, $spice, 10, CookingMoment::SIMMER)
            ->setDeletedAt(new \DateTimeImmutable());

        self::assertSame([], $this->builder()->build($history, 'fr'));
    }

    public function testDuoIsAttachedOnlyToItsExactPair(): void
    {
        $a = $this->spice(1);
        $b = $this->spice(2);
        $history = $this->history([$a, $b]);
        $this->withPreparation($history, $a, 20);
        $this->withCooking($history, $a, 10, CookingMoment::START);
        $this->withPreparation($history, $b, 21);
        $this->withCooking($history, $b, 11, CookingMoment::START);

        $steps = $this->builder([
            $this->duoRow(20, 10, 'Duo A'),
            $this->duoRow(21, 99, 'Autre paire'),
        ])->build($history, 'fr');

        self::assertSame('Duo A', $steps[0]->duo['title'] ?? null);
        self::assertNull($steps[1]->duo);
    }

    public function testViewExposesUsedMomentsAndFamilies(): void
    {
        $warm = $this->group(7);
        $fresh = $this->group(8);
        $a = $this->spice(1, $warm);
        $b = $this->spice(2, $fresh);
        $c = $this->spice(3, $warm);
        $history = $this->history([$a, $b, $c]);
        $this->withCooking($history, $a, 10, CookingMoment::FINISH);
        $this->withCooking($history, $b, 11, CookingMoment::START);
        $this->withCooking($history, $c, 12, CookingMoment::FINISH);

        $view = new RecipeView($this->builder()->build($history, 'fr'), new CulinaryContext());

        self::assertSame([CookingMoment::START, CookingMoment::FINISH], $view->usedMoments());
        self::assertSame(
            [[8, 1], [7, 2]],
            array_map(static fn ($f): array => [$f->group->getId(), $f->count], $view->families()),
        );
    }

    /**
     * @param list<array{spiceId: int, prepId: int, cookId: int, rank: int, prepTitle: string, title: string, effect: string, science: string, example: string}> $rows
     */
    private function builder(array $rows = []): RecipeStepsBuilder
    {
        $repository = $this->createStub(SpiceDuoRepository::class);
        $repository->method('findByTipIds')
            ->willReturn($rows);

        return new RecipeStepsBuilder($repository);
    }

    /**
     * @return array{spiceId: int, prepId: int, cookId: int, rank: int, prepTitle: string, title: string, effect: string, science: string, example: string}
     */
    private function duoRow(int $prepId, int $cookId, string $title): array
    {
        return [
            'spiceId' => 0,
            'prepId' => $prepId,
            'cookId' => $cookId,
            'rank' => 1,
            'prepTitle' => '',
            'title' => $title,
            'effect' => 'effet',
            'science' => 'science',
            'example' => 'exemple',
        ];
    }

    /**
     * @param list<Spices> $spices
     */
    private function history(array $spices): SpicyMatchHistory
    {
        $match = new SpicyMatch();
        foreach ($spices as $spice) {
            $match->addSpice($spice);
        }

        return new SpicyMatchHistory()
            ->setSpicyMatch($match);
    }

    private function withPreparation(SpicyMatchHistory $history, Spices $spice, int $id): PreparationTips
    {
        $tip = new PreparationTips()
            ->setSpice($spice);
        $this->setId($tip, $id);
        $history->addPreparationTip($tip);

        return $tip;
    }

    private function withCooking(SpicyMatchHistory $history, Spices $spice, int $id, CookingMoment $moment): CookingTips
    {
        $tip = new CookingTips()
            ->setSpice($spice)
            ->setMoment($moment);
        $this->setId($tip, $id);
        $history->addCookingTip($tip);

        return $tip;
    }

    private function spice(int $id, ?AromaticGroups $group = null): Spices
    {
        $spice = new Spices()
            ->setName('Épice ' . $id)
            ->setAromaticGroups($group);
        $this->setId($spice, $id);

        return $spice;
    }

    private function group(int $id): AromaticGroups
    {
        $group = new AromaticGroups();
        $this->setId($group, $id);

        return $group;
    }

    private function setId(object $entity, int $id): void
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);
    }
}
