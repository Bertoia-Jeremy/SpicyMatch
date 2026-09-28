<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;

interface QuestionGeneratorInterface
{
    public function supports(GameMode $mode): bool;

    /**
     * @param list<int> $excludeSpiceIds
     * @return array{
     *     type: string,
     *     prompt: string,
     *     baseSpice: array{id: int, name: string},
     *     options: list<array{id: int, name: string}>,
     *     correctAnswer: string,
     *     metadata: array<string, mixed>
     * }|null
     */
    public function generate(GameDifficulty $difficulty, array $excludeSpiceIds = []): ?array;
}
