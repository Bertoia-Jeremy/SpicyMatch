<?php

declare(strict_types=1);

namespace App\Twig\Components\Faq;

use App\Entity\FaqQuestion;
use App\ValueObject\FaqEntries;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent]
final class Accordion
{
    /**
     * @var list<FaqQuestion>
     */
    public array $questions = [];

    public bool $schema = true;

    public int $level = 3;

    #[PostMount]
    public function clampLevel(): void
    {
        $this->level = max(2, min(6, $this->level));
    }

    public function getEntries(): FaqEntries
    {
        return new FaqEntries($this->questions);
    }
}
