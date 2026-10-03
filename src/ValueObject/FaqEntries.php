<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\FaqQuestion;

final readonly class FaqEntries
{
    /**
     * @param list<FaqQuestion> $questions
     */
    public function __construct(
        public array $questions,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->questions === [];
    }
}
