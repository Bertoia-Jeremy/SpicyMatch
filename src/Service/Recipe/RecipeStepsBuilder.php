<?php

declare(strict_types=1);

namespace App\Service\Recipe;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpicyMatchHistory;
use App\Enum\CookingMoment;
use App\Repository\SpiceDuoRepository;
use App\ValueObject\Recipe\RecipeSpiceStep;

final readonly class RecipeStepsBuilder
{
    public function __construct(
        private SpiceDuoRepository $duoRepository,
    ) {
    }

    /**
     * @return list<RecipeSpiceStep>
     */
    public function build(SpicyMatchHistory $history, string $locale): array
    {
        $preparations = $this->indexBySpice($history->getPreparationTips());
        $cookings = $this->indexBySpice($history->getCookingTips());
        $duos = $this->indexDuos($preparations, $cookings, $locale);

        $steps = [];
        foreach ($history->getSpicyMatch()?->getSpices() ?? [] as $spice) {
            $spiceId = $spice->getId();
            $preparation = $spiceId === null ? null : ($preparations[$spiceId] ?? null);
            $cooking = $spiceId === null ? null : ($cookings[$spiceId] ?? null);
            if ($preparation === null && $cooking === null) {
                continue;
            }

            $duoKey = $preparation !== null && $cooking !== null ? $preparation->getId() . '|' . $cooking->getId() : null;
            $steps[] = new RecipeSpiceStep(
                $spice,
                $preparation,
                $cooking,
                $cooking?->getMoment() ?? CookingMoment::PRE,
                $duoKey === null ? null : ($duos[$duoKey] ?? null),
            );
        }

        usort($steps, static fn (RecipeSpiceStep $a, RecipeSpiceStep $b): int => $a->moment->value <=> $b->moment->value);

        return $steps;
    }

    /**
     * @template T of PreparationTips|CookingTips
     * @param iterable<T> $tips
     * @return array<int, T>
     */
    private function indexBySpice(iterable $tips): array
    {
        $indexed = [];
        foreach ($tips as $tip) {
            $spiceId = $tip->getSpice()?->getId();
            if ($spiceId === null || $tip->getDeletedAt() !== null) {
                continue;
            }
            $indexed[$spiceId] ??= $tip;
        }

        return $indexed;
    }

    /**
     * @param array<int, PreparationTips> $preparations
     * @param array<int, CookingTips> $cookings
     * @return array<string, array{title: string, effect: string, science: string, example: string}>
     */
    private function indexDuos(array $preparations, array $cookings, string $locale): array
    {
        $rows = $this->duoRepository->findByTipIds(
            array_values(array_map(static fn (PreparationTips $t): int => (int) $t->getId(), $preparations)),
            array_values(array_map(static fn (CookingTips $t): int => (int) $t->getId(), $cookings)),
            $locale,
        );

        $duos = [];
        foreach ($rows as $row) {
            $duos[$row['prepId'] . '|' . $row['cookId']] ??= [
                'title' => $row['title'],
                'effect' => $row['effect'],
                'science' => $row['science'],
                'example' => $row['example'],
            ];
        }

        return $duos;
    }
}
