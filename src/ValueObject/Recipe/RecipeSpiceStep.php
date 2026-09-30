<?php

declare(strict_types=1);

namespace App\ValueObject\Recipe;

use App\Entity\AromaticGroups;
use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Enum\CookingMoment;

final readonly class RecipeSpiceStep
{
    /**
     * @param array{title: string, effect: string, science: string, example: string}|null $duo
     */
    public function __construct(
        public Spices $spice,
        public ?PreparationTips $preparation,
        public ?CookingTips $cooking,
        public CookingMoment $moment,
        public ?array $duo = null,
    ) {
    }

    public function spiceId(): int
    {
        return (int) $this->spice->getId();
    }

    public function group(): ?AromaticGroups
    {
        return $this->spice->getAromaticGroups();
    }

    public function number(): string
    {
        return RecipeView::momentNumber($this->moment);
    }
}
