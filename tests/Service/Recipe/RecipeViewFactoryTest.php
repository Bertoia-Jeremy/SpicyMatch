<?php

declare(strict_types=1);

namespace App\Tests\Service\Recipe;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Repository\CompoundPhysicalRepositoryInterface;
use App\Repository\SpiceDuoRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Service\Match\CookingTimelineBuilder;
use App\Service\Match\MatchPipelineInterface;
use App\Service\Match\MatrixComparator;
use App\Service\Match\OavPartitionCalculator;
use App\Service\Recipe\RecipeStepsBuilder;
use App\Service\Recipe\RecipeViewFactory;
use App\ValueObject\Recipe\RecipeView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RecipeViewFactoryTest extends TestCase
{
    public function testOavComputationsAreSkippedWhenFlagIsOff(): void
    {
        $pipeline = $this->createMock(MatchPipelineInterface::class);
        $pipeline->expects($this->never())
            ->method(self::anything());
        $physical = $this->createMock(CompoundPhysicalRepositoryInterface::class);
        $physical->expects($this->never())
            ->method(self::anything());

        $view = $this->factory($pipeline, $physical, false)
            ->build($this->history(), 'fr');

        self::assertSame([], $view->matrixGrid);
        self::assertSame(RecipeView::EMPTY_TIMELINE, $view->cookingTimeline);
    }

    private function factory(MatchPipelineInterface $pipeline, CompoundPhysicalRepositoryInterface $physical, bool $enabled): RecipeViewFactory
    {
        $cache = new ArrayAdapter();
        $duos = $this->createStub(SpiceDuoRepository::class);
        $duos->method('findByTipIds')
            ->willReturn([]);

        return new RecipeViewFactory(
            $this->createStub(SpicyMatchHistoryRepository::class),
            new RecipeStepsBuilder($duos),
            new MatrixComparator($pipeline, $this->createStub(SpicesRepository::class), $cache),
            new CookingTimelineBuilder($physical, new OavPartitionCalculator(), $cache),
            $enabled,
        );
    }

    private function history(): SpicyMatchHistory
    {
        $spice = new Spices()
            ->setName('Cannelle');
        new \ReflectionProperty(Spices::class, 'id')->setValue($spice, 1);

        return new SpicyMatchHistory()
            ->setSpicyMatch(new SpicyMatch()->addSpice($spice));
    }
}
