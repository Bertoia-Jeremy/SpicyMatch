<?php

declare(strict_types=1);

namespace App\ValueObject\Recipe;

use App\Enum\CookingMoment;
use App\Service\Match\TimelineEntry;
use App\ValueObject\Match\CulinaryContext;

final readonly class RecipeView
{
    public const array EMPTY_TIMELINE = [
        'head' => [],
        'heart' => [],
        'base' => [],
        'unknown' => [],
    ];

    /**
     * @param list<RecipeSpiceStep> $steps
     * @param list<array{id: int, name: string, scores: array<string, int>}> $matrixGrid
     * @param array{head: list<TimelineEntry>, heart: list<TimelineEntry>, base: list<TimelineEntry>, unknown: list<TimelineEntry>} $cookingTimeline
     */
    public function __construct(
        public array $steps,
        public CulinaryContext $culinaryContext,
        public array $matrixGrid = [],
        public array $cookingTimeline = self::EMPTY_TIMELINE,
    ) {
    }

    public static function momentNumber(CookingMoment $moment): string
    {
        return \sprintf('%02d', $moment->value + 1);
    }

    /**
     * @return list<CookingMoment>
     */
    public function moments(): array
    {
        return CookingMoment::cases();
    }

    /**
     * @return list<RecipeSpiceStep>
     */
    public function stepsAt(CookingMoment $moment): array
    {
        return array_values(array_filter($this->steps, static fn (RecipeSpiceStep $s): bool => $s->moment === $moment));
    }

    /**
     * @return list<CookingMoment>
     */
    public function usedMoments(): array
    {
        return array_values(array_filter(
            CookingMoment::cases(),
            fn (CookingMoment $m): bool => $this->stepsAt($m) !== [],
        ));
    }

    /**
     * @return list<RecipeFamily>
     */
    public function families(): array
    {
        $groups = [];
        $counts = [];
        foreach ($this->steps as $step) {
            $group = $step->group();
            $id = $group?->getId();
            if ($group === null || $id === null) {
                continue;
            }
            $groups[$id] ??= $group;
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        return array_map(
            static fn (int $id): RecipeFamily => new RecipeFamily($groups[$id], $counts[$id]),
            array_keys($groups),
        );
    }

    public function hasKinetics(): bool
    {
        return $this->cookingTimeline['head'] !== []
            || $this->cookingTimeline['heart'] !== []
            || $this->cookingTimeline['base'] !== [];
    }
}
