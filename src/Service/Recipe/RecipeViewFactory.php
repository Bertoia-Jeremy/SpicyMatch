<?php

declare(strict_types=1);

namespace App\Service\Recipe;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Exception\Match\InvalidMortarException;
use App\Repository\SpicyMatchHistoryRepository;
use App\Service\Match\CookingTimelineBuilder;
use App\Service\Match\MatrixComparator;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use App\ValueObject\Recipe\RecipeView;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RecipeViewFactory
{
    private const int MATRIX_MAX_SPICES = 10;

    public function __construct(
        private SpicyMatchHistoryRepository $historyRepository,
        private RecipeStepsBuilder $stepsBuilder,
        private MatrixComparator $matrixComparator,
        private CookingTimelineBuilder $timelineBuilder,
        #[Autowire(param: 'app.oav_context_enabled')]
        private bool $oavContextEnabled,
    ) {
    }

    public function build(SpicyMatchHistory $history, string $locale): RecipeView
    {
        $this->historyRepository->preloadForRecipe($history, $locale);
        $match = $history->getSpicyMatch() ?? throw new \LogicException('History without SpicyMatch');
        $context = $match->getCulinaryContext();
        $spices = $match->getSpices()
            ->toArray();

        if (! $this->oavContextEnabled) {
            return new RecipeView($this->stepsBuilder->build($history, $locale), $this->sharedCompounds($spices), $context);
        }

        return new RecipeView(
            $this->stepsBuilder->build($history, $locale),
            $this->sharedCompounds($spices),
            $context,
            $this->matrixGrid($match, $context, $locale),
            $this->timelineBuilder->build($this->mortarCompounds($spices), $context),
        );
    }

    /**
     * @param list<Spices> $spices
     * @return list<AromaticCompound>
     */
    private function sharedCompounds(array $spices): array
    {
        $shared = null;
        foreach ($spices as $spice) {
            $compounds = $spice->getAromaticsCompounds()
                ->toArray();
            $shared = $shared === null
                ? $compounds
                : array_uintersect($shared, $compounds, static fn (AromaticCompound $a, AromaticCompound $b): int => $a->getId() <=> $b->getId());
        }

        return array_values($shared ?? []);
    }

    /**
     * @param list<Spices> $spices
     * @return list<AromaticCompound>
     */
    private function mortarCompounds(array $spices): array
    {
        $compounds = [];
        foreach ($spices as $spice) {
            foreach ($spice->getAromaticsCompounds() as $compound) {
                $id = $compound->getId();
                if ($id !== null) {
                    $compounds[$id] ??= $compound;
                }
            }
        }

        return array_values($compounds);
    }

    /**
     * @return list<array{id: int, name: string, scores: array<string, int>}>
     */
    private function matrixGrid(SpicyMatch $match, CulinaryContext $context, string $locale): array
    {
        $ids = $match->getSpices()
            ->map(static fn (Spices $s): ?int => $s->getId())
            ->filter(static fn (?int $id): bool => $id !== null)
            ->toArray();
        $bounded = \array_slice(array_values($ids), 0, self::MATRIX_MAX_SPICES);
        if ($bounded === []) {
            return [];
        }

        try {
            return $this->matrixComparator->buildGrid(
                $this->matrixComparator->compare(new MortarIds($bounded), $context, limit: 5, locale: $locale)
            );
        } catch (InvalidMortarException) {
            return [];
        }
    }
}
