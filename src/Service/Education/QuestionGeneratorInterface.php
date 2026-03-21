<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;

interface QuestionGeneratorInterface
{
    public function supports(GameMode $mode): bool;

    /**
     * Generate a question for the given difficulty.
     *
     * @param list<int> $excludeSpiceIds spice IDs already used in this session (to avoid repeats)
     *
     * @return array{
     *     type: string,
     *     prompt: string,
     *     baseSpice: array{id: int, name: string},
     *     options: list<array{id: int, name: string}>,
     *     correctAnswer: string,
     *     metadata: array<string, mixed>
     * }|null null if not enough data to generate a question
     */
    public function generate(GameDifficulty $difficulty, array $excludeSpiceIds = []): ?array;
}
